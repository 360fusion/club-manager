<?php

namespace Launchpad\Sso;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Launchpad\Accounts\AccountRegistration;
use Launchpad\Models\Membership;
use Launchpad\Models\ScimIdentity;
use Launchpad\Models\SsoConnection;
use Launchpad\Workspaces\Activity;
use Launchpad\Workspaces\Role;

/**
 * SCIM 2.0 user provisioning for a team with single sign-on: its directory (Okta, Entra) adds people, changes their name and address, and
 * turns them off. Adding makes the account if there is none (verified, no password, as a single sign-on would) and puts the person in the
 * team with the connection's default role; turning off removes them from this team only, since the account may be in others. Addresses
 * must be on the team's verified domain, and the team's owner is never removed this way. Groups are not supported.
 */
final class Scim
{
    public const USER = 'urn:ietf:params:scim:schemas:core:2.0:User';

    public const LIST = 'urn:ietf:params:scim:api:messages:2.0:ListResponse';

    public const ERROR = 'urn:ietf:params:scim:api:messages:2.0:Error';

    public const PATCH = 'urn:ietf:params:scim:api:messages:2.0:PatchOp';

    public static function json(mixed $body, int $status = 200): JsonResponse
    {
        return response()->json($body, $status, ['Content-Type' => 'application/scim+json']);
    }

    public static function error(int $status, string $detail, ?string $type = null): JsonResponse
    {
        return self::json(array_filter(['schemas' => [self::ERROR], 'status' => (string) $status, 'scimType' => $type, 'detail' => $detail]), $status);
    }

    /** @return array<string, mixed> the SCIM User for this person */
    public static function resource(ScimIdentity $identity): array
    {
        $user = $identity->user;
        [$given, $family] = array_pad(explode(' ', (string) $user->name, 2), 2, '');

        return [
            'schemas' => [self::USER],
            'id' => (string) $identity->id,
            'externalId' => $identity->external_id,
            'userName' => $user->email,
            'name' => ['formatted' => $user->name, 'givenName' => $given, 'familyName' => $family],
            'displayName' => $user->name,
            'emails' => [['value' => $user->email, 'type' => 'work', 'primary' => true]],
            'active' => $identity->active,
            'meta' => ['resourceType' => 'User', 'created' => $identity->created_at->toIso8601String(), 'lastModified' => $identity->updated_at->toIso8601String(),
                'location' => route('scim.users.show', $identity->id)],
        ];
    }

    /** The address a SCIM body gives: userName, else the primary (or first) email */
    public static function emailOf(array $body): ?string
    {
        $emails = collect($body['emails'] ?? []);
        $email = $body['userName'] ?? ($emails->firstWhere('primary', true) ?? $emails->first())['value'] ?? null;

        return is_string($email) ? AccountRegistration::normalizeEmail($email) : null;
    }

    public static function nameOf(array $body): ?string
    {
        $name = $body['name']['formatted'] ?? trim(($body['name']['givenName'] ?? '').' '.($body['name']['familyName'] ?? '')) ?: ($body['displayName'] ?? null);

        return is_string($name) && trim($name) !== '' ? AccountRegistration::cleanName($name) : null;
    }

    public static function onDomain(SsoConnection $connection, string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) && strtolower(Str::afterLast($email, '@')) === $connection->domain;
    }

    /** Adds a person, or finds the one already added for that address */
    public function create(SsoConnection $connection, array $body, AccountRegistration $accounts): ScimIdentity
    {
        $email = self::emailOf($body);

        return DB::transaction(function () use ($connection, $body, $email, $accounts) {
            $user = AccountRegistration::findByEmail($email);

            if ($user === null) {
                $user = $accounts->register(['name' => self::nameOf($body) ?? '', 'email' => $email, 'password' => Str::random(40)], emailVerified: true, passwordChosen: false);
            } elseif (! $user->hasVerifiedEmail()) {
                // the team's own directory vouches for this address: whatever someone else set up on it unproved is taken back
                $accounts->takeBack($user);
            }

            $identity = ScimIdentity::firstOrNew(['sso_connection_id' => $connection->id, 'user_id' => $user->getKey()]);
            $identity->fill(['external_id' => $body['externalId'] ?? $identity->external_id]);
            $this->setActive($connection, $identity, ($body['active'] ?? true) !== false);

            return $identity;
        });
    }

    /** What a PUT or a PATCH asks for, already worked out: name, email, externalId, active (each only if given) */
    public function apply(SsoConnection $connection, ScimIdentity $identity, array $changes): void
    {
        DB::transaction(function () use ($connection, $identity, $changes) {
            $user = $identity->user;

            if (isset($changes['name'])) {
                $user->name = $changes['name'];
            }

            if (isset($changes['email']) && $changes['email'] !== $user->email) {
                $user->email = $changes['email'];
            }

            $user->isDirty() && $user->save();

            if (array_key_exists('externalId', $changes)) {
                $identity->external_id = $changes['externalId'];
            }

            $this->setActive($connection, $identity, $changes['active'] ?? $identity->active ?? true);
        });
    }

    public function delete(SsoConnection $connection, ScimIdentity $identity): void
    {
        $this->setActive($connection, $identity, false);
        $identity->delete();
    }

    /** On: in the team with the default role. Off: out of this team (the owner stays) */
    private function setActive(SsoConnection $connection, ScimIdentity $identity, bool $active): void
    {
        $was = $identity->exists ? $identity->getOriginal('active') : null;
        $identity->active = $active;
        $identity->save();

        $workspace = $connection->workspace;
        $user = $identity->user;

        if ($active) {
            $membership = Activity::silently(fn () => Membership::firstOrCreate(['workspace_id' => $workspace->id, 'user_id' => $user->getKey()], ['role' => Role::tryFrom($connection->default_role) ?? Role::Member]));
            $membership->wasRecentlyCreated && Activity::record($workspace, 'member.added', "added {$user->email} to the team", 'Directory sync');
        } elseif ($was !== false) {
            // one by one, so the membership's own clean-up runs; the line in the activity log is this one
            $removed = Activity::silently(fn () => Membership::where(['workspace_id' => $workspace->id, 'user_id' => $user->getKey()])->where('role', '!=', Role::Owner->value)->get()->each->delete()->count());
            $removed && Activity::record($workspace, 'member.removed', "removed {$user->email} from the team", 'Directory sync');
        }
    }
}
