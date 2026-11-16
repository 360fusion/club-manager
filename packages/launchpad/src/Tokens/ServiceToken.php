<?php

namespace Launchpad\Tokens;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Launchpad\Billing\Entitlements;
use Launchpad\Models\Membership;
use Launchpad\Models\Workspace;

/**
 * A short-lived signed token that tells another service (one the app does not run itself, such as a Node API) who is asking and what
 * they may do: the person, the workspace, their role in it and the limits of its plan. The service checks the signature and the expiry and
 * trusts the claims; it never sees a password or a session. HS256 with a secret both sides hold (config launchpad.service_token.secret).
 *
 * Claims: iss, aud, sub (user id), wid, wslug, role, plan, limits, seats, iat, nbf, exp, jti. No email or name: the service learns those
 * from the app's sync, not from every request.
 */
class ServiceToken
{
    public function __construct(private readonly Entitlements $entitlements) {}

    public function enabled(): bool
    {
        return strlen((string) config('launchpad.service_token.secret')) >= 32;
    }

    /** @throws \RuntimeException when no usable secret is set */
    public function issue(Model $user, Workspace $workspace): string
    {
        $role = Membership::where(['workspace_id' => $workspace->id, 'user_id' => $user->getKey()])->first()?->role;
        abort_if($role === null, 403, 'Not a member of that workspace.');

        $now = time();
        $plan = $this->entitlements->plan($workspace);
        $limits = [];
        foreach (array_keys($plan->limits) as $name) {
            $limits[$name] = $this->entitlements->limit($workspace, $name);
        }

        return JWT::encode([
            'iss' => $this->issuer(),
            'aud' => $this->audience(),
            'sub' => (string) $user->getKey(),
            'wid' => (string) $workspace->id,
            'wslug' => $workspace->slug,
            'role' => $role->value,
            'plan' => $plan->key,
            'limits' => $limits,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->ttl(),
            'jti' => (string) Str::uuid(),
        ], $this->secret(), 'HS256');
    }

    /** The claims of a good token, or null for one that is expired, forged, not for this audience or from another issuer */
    public function verify(string $token): ?object
    {
        try {
            $claims = JWT::decode($token, new Key($this->secret(), 'HS256'));
        } catch (\Throwable) {
            return null;
        }

        return ($claims->iss ?? null) === $this->issuer() && ($claims->aud ?? null) === $this->audience() ? $claims : null;
    }

    public function ttl(): int
    {
        return max(30, min(3600, (int) config('launchpad.service_token.ttl', 300)));
    }

    private function issuer(): string
    {
        return (string) (config('launchpad.service_token.issuer') ?: config('app.url'));
    }

    private function audience(): string
    {
        return (string) config('launchpad.service_token.audience', 'service');
    }

    private function secret(): string
    {
        $secret = (string) config('launchpad.service_token.secret');

        if (strlen($secret) < 32) {
            throw new \RuntimeException('LAUNCHPAD_SERVICE_TOKEN_SECRET must be at least 32 characters.');
        }

        return $secret;
    }
}
