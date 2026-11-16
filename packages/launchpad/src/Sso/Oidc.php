<?php

namespace Launchpad\Sso;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Launchpad\Models\SsoConnection;
use Launchpad\Webhooks\Webhooks;
use Throwable;

/**
 * OpenID Connect, authorization code with PKCE: the provider's discovery document, the address to send a person to, and the exchange of the code
 * for a checked ID token. Every address fetched must be public https (the same guard as webhooks), so a team cannot make the app call inside its own network.
 * shortcut: the guard resolves the host before the request and the HTTP client resolves it again; pin the address (as webhook delivery does) if DNS rebinding matters.
 */
final class Oidc
{
    /**
     * @return array{issuer: string, authorization_endpoint: string, token_endpoint: string, jwks_uri: string}
     *
     * @throws InvalidArgumentException when the issuer is not a public https address or does not answer as a provider
     */
    public static function discover(string $issuer): array
    {
        $issuer = rtrim($issuer, '/');

        return Cache::remember('launchpad.oidc.'.sha1($issuer), 3600, function () use ($issuer) {
            $document = self::getJson($issuer.'/.well-known/openid-configuration');

            foreach (['issuer', 'authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $key) {
                if (! is_string($document[$key] ?? null) || $document[$key] === '') {
                    throw new InvalidArgumentException("The provider's discovery document has no {$key}.");
                }
            }

            if (rtrim($document['issuer'], '/') !== $issuer) {
                throw new InvalidArgumentException('The provider names a different issuer: '.$document['issuer']);
            }

            foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $key) {
                Webhooks::guard($document[$key]);
            }

            return $document;
        });
    }

    /** The provider's sign-in page for this attempt */
    public static function authorizationUrl(SsoConnection $connection, string $state, string $nonce, string $verifier, string $redirectUri, string $email): string
    {
        $endpoint = self::discover($connection->issuer)['authorization_endpoint'];

        return $endpoint.(str_contains($endpoint, '?') ? '&' : '?').http_build_query([
            'response_type' => 'code', 'client_id' => $connection->client_id, 'redirect_uri' => $redirectUri,
            'scope' => 'openid email profile', 'state' => $state, 'nonce' => $nonce, 'login_hint' => $email,
            'code_challenge' => self::challenge($verifier), 'code_challenge_method' => 'S256',
        ]);
    }

    public static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public static function newVerifier(): string
    {
        return Str::random(64);
    }

    /**
     * Trades the code for an ID token and checks it: the signature against the provider's keys, the issuer, the audience (and authorised party),
     * the time, the nonce, and an email address that is verified and on the connection's domain.
     *
     * @return array<string, mixed> the token's claims
     *
     * @throws InvalidArgumentException naming what did not hold up
     */
    public static function exchange(SsoConnection $connection, string $code, string $verifier, string $nonce, string $redirectUri): array
    {
        $provider = self::discover($connection->issuer);
        Webhooks::guard($provider['token_endpoint']);

        try {
            $response = Http::asForm()->acceptJson()->timeout(10)->withoutRedirecting()->post($provider['token_endpoint'], [
                'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirectUri,
                'client_id' => $connection->client_id, 'client_secret' => $connection->client_secret, 'code_verifier' => $verifier,
            ]);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('The provider could not be reached.', 0, $e);
        }
        $idToken = $response->successful() ? $response->json('id_token') : null;

        if (! is_string($idToken) || $idToken === '') {
            throw new InvalidArgumentException('The provider did not give an ID token.');
        }

        $claims = self::verify($idToken, $provider);

        $audience = (array) ($claims['aud'] ?? []);
        if (! in_array($connection->client_id, $audience, true) || (count($audience) > 1 && ($claims['azp'] ?? null) !== $connection->client_id)) {
            throw new InvalidArgumentException('The ID token is for another application.');
        }
        if (rtrim((string) ($claims['iss'] ?? ''), '/') !== rtrim($provider['issuer'], '/')) {
            throw new InvalidArgumentException('The ID token comes from another issuer.');
        }
        if (! hash_equals($nonce, (string) ($claims['nonce'] ?? ''))) {
            throw new InvalidArgumentException('The ID token is not for this sign-in.');
        }

        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('The provider did not share an email address.');
        }
        if (array_key_exists('email_verified', $claims) && $claims['email_verified'] !== true && $claims['email_verified'] !== 'true') {
            throw new InvalidArgumentException('The provider has not verified the email address.');
        }
        if (Str::afterLast($email, '@') !== $connection->domain) {
            throw new InvalidArgumentException('The email address is not on the team\'s domain.');
        }

        return ['email' => $email] + $claims;
    }

    /** @param  array<string, string>  $provider */
    private static function verify(string $idToken, array $provider): array
    {
        $leeway = JWT::$leeway;
        JWT::$leeway = 60;

        try {
            // keys rotate: a token signed with a key not seen yet gets one fresh look at the key set
            foreach ([false, true] as $fresh) {
                try {
                    return (array) JWT::decode($idToken, JWK::parseKeySet(self::keys($provider['jwks_uri'], $fresh), 'RS256'));
                } catch (\UnexpectedValueException $e) {
                    if ($fresh || ! str_contains($e->getMessage(), '"kid"')) {
                        throw $e;
                    }
                }
            }
        } catch (Throwable $e) {
            throw new InvalidArgumentException('The ID token did not check out: '.$e->getMessage(), 0, $e);
        } finally {
            JWT::$leeway = $leeway;
        }

        throw new InvalidArgumentException('The ID token did not check out.');
    }

    private static function keys(string $uri, bool $fresh): array
    {
        $key = 'launchpad.oidc.jwks.'.sha1($uri);
        $fresh && Cache::forget($key);

        return Cache::remember($key, 3600, fn () => self::getJson($uri));
    }

    private static function getJson(string $url): array
    {
        Webhooks::guard($url);

        try {
            $response = Http::acceptJson()->timeout(10)->withoutRedirecting()->get($url);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('The provider could not be reached.', 0, $e);
        }

        if (! $response->successful() || ! is_array($response->json())) {
            throw new InvalidArgumentException('The provider did not answer at '.$url.'.');
        }

        return $response->json();
    }
}
