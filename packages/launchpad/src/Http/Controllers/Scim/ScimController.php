<?php

namespace Launchpad\Http\Controllers\Scim;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Launchpad\Accounts\AccountRegistration;
use Launchpad\Models\ScimIdentity;
use Launchpad\Models\SsoConnection;
use Launchpad\Sso\Scim;

/** SCIM 2.0 at /scim/v2 for one team, found from the bearer token (Launchpad\Http\Middleware\AuthenticateScim). Users only. */
class ScimController
{
    public function serviceProviderConfig(): JsonResponse
    {
        return Scim::json([
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:ServiceProviderConfig'],
            'patch' => ['supported' => true], 'bulk' => ['supported' => false, 'maxOperations' => 0, 'maxPayloadSize' => 0],
            'filter' => ['supported' => true, 'maxResults' => 200], 'changePassword' => ['supported' => false],
            'sort' => ['supported' => false], 'etag' => ['supported' => false],
            'authenticationSchemes' => [['type' => 'oauthbearertoken', 'name' => 'Bearer token', 'description' => 'The token from the team\'s single sign-on page']],
        ]);
    }

    public function resourceTypes(): JsonResponse
    {
        return Scim::json(['schemas' => [Scim::LIST], 'totalResults' => 1, 'Resources' => [[
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:ResourceType'], 'id' => 'User', 'name' => 'User', 'endpoint' => '/Users', 'schema' => Scim::USER,
        ]]]);
    }

    public function schemas(): JsonResponse
    {
        $attribute = fn (string $name, string $type = 'string', bool $required = false) => ['name' => $name, 'type' => $type, 'multiValued' => false, 'required' => $required, 'mutability' => 'readWrite', 'returned' => 'default'];

        return Scim::json(['schemas' => [Scim::LIST], 'totalResults' => 1, 'Resources' => [[
            'id' => Scim::USER, 'name' => 'User', 'attributes' => [$attribute('userName', required: true), $attribute('name', 'complex'), $attribute('displayName'), $attribute('emails', 'complex'), $attribute('active', 'boolean'), $attribute('externalId')],
        ]]]);
    }

    public function index(Request $request): JsonResponse
    {
        $connection = $this->connection($request);
        $query = ScimIdentity::where('sso_connection_id', $connection->id)->with('user')->orderBy('id');

        if ($filter = trim((string) $request->query('filter'))) {
            if (! preg_match('/^(userName|externalId|emails\.value)\s+eq\s+"([^"]*)"$/i', $filter, $m)) {
                return Scim::error(400, 'Only userName, externalId or emails.value eq "..." filters are supported.', 'invalidFilter');
            }

            strtolower($m[1]) === 'externalid'
                ? $query->where('external_id', $m[2])
                : $query->whereHas('user', fn ($q) => $q->where('email', AccountRegistration::normalizeEmail($m[2])));
        }

        $total = (clone $query)->count();
        $start = max(1, (int) $request->query('startIndex', 1));
        $count = min(200, max(0, (int) $request->query('count', 100)));
        $page = $query->skip($start - 1)->take($count)->get();

        return Scim::json(['schemas' => [Scim::LIST], 'totalResults' => $total, 'startIndex' => $start, 'itemsPerPage' => $page->count(),
            'Resources' => $page->map(fn ($identity) => Scim::resource($identity))->all()]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return ($identity = $this->find($request, $id)) ? Scim::json(Scim::resource($identity)) : Scim::error(404, 'No such user.');
    }

    public function store(Request $request, Scim $scim, AccountRegistration $accounts): JsonResponse
    {
        $connection = $this->connection($request);
        $body = $request->json()->all();
        $email = Scim::emailOf($body);

        if ($email === null || ! Scim::onDomain($connection, $email)) {
            return Scim::error(400, "The address must be on {$connection->domain}.", 'invalidValue');
        }

        if (ScimIdentity::where('sso_connection_id', $connection->id)->whereHas('user', fn ($q) => $q->where('email', $email))->exists()) {
            return Scim::error(409, 'That user is already provisioned.', 'uniqueness');
        }

        $identity = $scim->create($connection, $body, $accounts);

        return Scim::json(Scim::resource($identity->load('user')), 201)->header('Location', route('scim.users.show', $identity->id));
    }

    public function replace(Request $request, int $id, Scim $scim): JsonResponse
    {
        $identity = $this->find($request, $id) ?? abort(Scim::error(404, 'No such user.'));
        $body = $request->json()->all();

        return $this->change($request, $identity, $scim, [
            'email' => Scim::emailOf($body), 'name' => Scim::nameOf($body), 'externalId' => $body['externalId'] ?? null, 'active' => ($body['active'] ?? true) !== false,
        ]);
    }

    public function patch(Request $request, int $id, Scim $scim): JsonResponse
    {
        $identity = $this->find($request, $id) ?? abort(Scim::error(404, 'No such user.'));
        $changes = [];

        foreach ((array) $request->json('Operations', []) as $op) {
            $kind = strtolower((string) ($op['op'] ?? ''));

            if (! in_array($kind, ['add', 'replace'], true)) {
                return Scim::error(400, 'Only add and replace operations are supported.', 'invalidValue');
            }

            // with no path the value is an object of attributes; with one it is that attribute's value
            $values = isset($op['path']) ? [$op['path'] => $op['value'] ?? null] : (array) ($op['value'] ?? []);

            foreach ($values as $path => $value) {
                $path = strtolower((string) $path);
                match (true) {
                    $path === 'active' => $changes['active'] = filter_var($value, FILTER_VALIDATE_BOOLEAN),
                    $path === 'externalid' => $changes['externalId'] = is_scalar($value) ? (string) $value : null,
                    in_array($path, ['username', 'emails[type eq "work"].value', 'emails'], true) => $changes['email'] = Scim::emailOf(is_array($value) ? ['emails' => $value] : ['userName' => $value]),
                    $path === 'name' => $changes['name'] = Scim::nameOf(['name' => (array) $value]),
                    in_array($path, ['displayname', 'name.formatted'], true) => $changes['name'] = Scim::nameOf(['displayName' => $value]),
                    $path === 'name.givenname' => $changes['given'] = (string) $value,
                    $path === 'name.familyname' => $changes['family'] = (string) $value,
                    default => null, // an attribute this does not keep is ignored, as SCIM allows
                };
            }
        }

        if (isset($changes['given']) || isset($changes['family'])) {
            [$given, $family] = array_pad(explode(' ', (string) $identity->user->name, 2), 2, '');
            $changes['name'] ??= Scim::nameOf(['name' => ['givenName' => $changes['given'] ?? $given, 'familyName' => $changes['family'] ?? $family]]);
        }

        return $this->change($request, $identity, $scim, array_intersect_key($changes, array_flip(['email', 'name', 'externalId', 'active'])));
    }

    public function destroy(Request $request, int $id, Scim $scim): Response|JsonResponse
    {
        $identity = $this->find($request, $id) ?? abort(Scim::error(404, 'No such user.'));
        $scim->delete($this->connection($request), $identity);

        return response()->noContent();
    }

    private function change(Request $request, ScimIdentity $identity, Scim $scim, array $changes): JsonResponse
    {
        $connection = $this->connection($request);
        $changes = array_filter($changes, fn ($v, $k) => $v !== null || $k === 'externalId', ARRAY_FILTER_USE_BOTH);

        if (isset($changes['email']) && $changes['email'] !== $identity->user->email) {
            if (! Scim::onDomain($connection, $changes['email'])) {
                return Scim::error(400, "The address must be on {$connection->domain}.", 'invalidValue');
            }

            if (AccountRegistration::findByEmail($changes['email'])) {
                return Scim::error(409, 'Another account has that address.', 'uniqueness');
            }
        }

        $scim->apply($connection, $identity, $changes);

        return Scim::json(Scim::resource($identity->fresh('user')));
    }

    private function connection(Request $request): SsoConnection
    {
        return $request->attributes->get('launchpad.scim');
    }

    private function find(Request $request, int $id): ?ScimIdentity
    {
        return ScimIdentity::where('sso_connection_id', $this->connection($request)->id)->with('user')->find($id);
    }
}
