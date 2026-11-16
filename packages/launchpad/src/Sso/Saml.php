<?php

namespace Launchpad\Sso;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Launchpad\Models\SsoConnection;
use Launchpad\Webhooks\Webhooks;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\IdPMetadataParser;
use OneLogin\Saml2\Utils;
use Throwable;

/**
 * SAML 2.0 for a team whose provider does not do OpenID Connect, through onelogin/php-saml (suggested, not required: without it SAML is
 * not offered). Every check is on: the assertion must be signed with the certificate saved for the team, be for this site (audience and
 * destination), answer a request this site made (InResponseTo, so a response cannot be replayed into another sign-in), be inside its time
 * window, and be used once; the email must be on the team's verified domain. The provider posts back from its own site, where the browser
 * does not send the session cookie, so the request being answered is kept in the cache under a one-time RelayState token.
 * Not supported: sign-ins the provider starts on its own, signed requests, encrypted assertions, single log-out.
 */
final class Saml
{
    private const MINUTES = 10;

    public static function available(): bool
    {
        return class_exists(Auth::class);
    }

    /** @return array{issuer: string, sso_url: string, certificate: string} from the provider's metadata */
    public static function parseMetadata(string $xml): array
    {
        try {
            $idp = IdPMetadataParser::parseXML($xml)['idp'] ?? [];
        } catch (Throwable) {
            throw new InvalidArgumentException('The metadata is not valid XML.');
        }

        $certificate = $idp['x509cert'] ?? ($idp['x509certMulti']['signing'][0] ?? null);

        if (empty($idp['entityId']) || empty($idp['singleSignOnService']['url']) || empty($certificate)) {
            throw new InvalidArgumentException('The metadata needs the entity ID, a sign-in address and a signing certificate.');
        }

        if (! str_starts_with($idp['singleSignOnService']['url'], 'https://')) {
            throw new InvalidArgumentException('The sign-in address must be https.');
        }

        return ['issuer' => $idp['entityId'], 'sso_url' => $idp['singleSignOnService']['url'], 'certificate' => $certificate];
    }

    /** The provider's metadata from its address, fetched the way webhooks are (public https only, no redirects) */
    public static function fetchMetadata(string $url): array
    {
        Webhooks::guard($url);

        try {
            $response = Http::timeout(10)->withoutRedirecting()->get($url);
        } catch (Throwable) {
            throw new InvalidArgumentException('The metadata address could not be reached.');
        }

        if (! $response->successful() || strlen($response->body()) > 1_000_000) {
            throw new InvalidArgumentException('The metadata address did not answer with metadata.');
        }

        return self::parseMetadata($response->body());
    }

    /** @return array<string, mixed> onelogin/php-saml settings for the team's connection */
    public static function settings(SsoConnection $connection): array
    {
        return [
            'strict' => true,
            'sp' => [
                'entityId' => route('sso.saml.metadata', $connection),
                'assertionConsumerService' => ['url' => route('sso.saml.acs', $connection)],
                'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
            ],
            'idp' => ['entityId' => $connection->issuer, 'singleSignOnService' => ['url' => $connection->saml_sso_url], 'x509cert' => $connection->saml_certificate],
            'security' => [
                'wantAssertionsSigned' => true, 'wantMessagesSigned' => false, 'wantNameIdEncrypted' => false, 'wantAssertionsEncrypted' => false,
                'rejectUnsolicitedResponsesWithInResponseTo' => true, 'wantXMLValidation' => true, 'destinationStrictlyMatches' => true,
                'requestedAuthnContext' => false, 'signatureAlgorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
                'digestAlgorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256',
            ],
        ];
    }

    /** The provider's sign-in address for this request; the request's id waits in the cache under the RelayState token */
    public static function start(SsoConnection $connection, string $email): string
    {
        $relay = Str::random(40);
        $auth = new Auth(self::settings($connection));
        $url = $auth->login($relay, ['login_hint' => $email], false, false, true);

        Cache::put("launchpad.saml.{$relay}", ['connection' => $connection->id, 'request' => $auth->getLastRequestID()], now()->addMinutes(self::MINUTES));

        return $url;
    }

    /**
     * Checks the provider's answer.
     *
     * @return array{email: string, name: ?string}
     *
     * @throws InvalidArgumentException when anything does not check out
     */
    public static function finish(Request $request, SsoConnection $connection): array
    {
        $pending = Cache::pull('launchpad.saml.'.$request->input('RelayState', ''));

        if (! is_array($pending) || $pending['connection'] !== $connection->id) {
            throw new InvalidArgumentException('This answer is not for a sign-in this site started, or it has expired.');
        }

        $auth = new Auth(self::settings($connection));
        [$post, $uri] = [$_POST, $_SERVER['REQUEST_URI'] ?? null];

        try {
            // the library reads the request itself: give it what Laravel saw, which is right behind a trusted proxy too
            $_POST = ['SAMLResponse' => (string) $request->input('SAMLResponse')];
            $_SERVER['REQUEST_URI'] = $request->getRequestUri();
            Utils::setSelfProtocol($request->getScheme());
            Utils::setSelfHost($request->getHost());
            Utils::setSelfPort($request->getPort());
            $auth->processResponse($pending['request']);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('The answer could not be read: '.$e->getMessage(), 0, $e);
        } finally {
            $_POST = $post;
            $_SERVER['REQUEST_URI'] = $uri;
        }

        if (! $auth->isAuthenticated() || $auth->getErrors()) {
            throw new InvalidArgumentException('The answer did not check out: '.($auth->getLastErrorReason() ?? implode(', ', $auth->getErrors())));
        }

        // each assertion is used once, for as long as it would be valid
        $expires = $auth->getLastAssertionNotOnOrAfter();
        if (! Cache::add('launchpad.saml.used.'.hash('sha256', $connection->id.'|'.$auth->getLastAssertionId()), true, $expires ? max(60, $expires - time() + 300) : 3600)) {
            throw new InvalidArgumentException('This answer has been used already.');
        }

        $attributes = $auth->getAttributes();
        $first = fn (array $names) => collect($names)->map(fn ($n) => $attributes[$n][0] ?? null)->first(fn ($v) => is_string($v) && $v !== '');
        $nameId = (string) $auth->getNameId();
        $email = strtolower(trim(filter_var($nameId, FILTER_VALIDATE_EMAIL) ? $nameId : (string) $first(['email', 'mail', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress'])));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! hash_equals($connection->domain, Str::afterLast($email, '@'))) {
            throw new InvalidArgumentException('The email address is missing or not on the team\'s domain.');
        }

        $given = $first(['givenName', 'firstName', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname']);
        $family = $first(['surname', 'sn', 'lastName', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname']);
        $name = $first(['displayName', 'name', 'http://schemas.microsoft.com/identity/claims/displayname']) ?? (trim("{$given} {$family}") ?: null);

        return ['email' => $email, 'name' => $name];
    }
}
