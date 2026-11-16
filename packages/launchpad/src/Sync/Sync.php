<?php

namespace Launchpad\Sync;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Launchpad\Billing\Entitlements;
use Launchpad\Models\CustomRole;
use Launchpad\Models\Membership;
use Launchpad\Models\SyncEvent;
use Launchpad\Models\Workspace;

/**
 * Telling another service what changed here: a person made, changed or removed; a workspace; who is in it with which role; which plan it is on and its
 * limits. Each change is written to `sync_events` in the request that made it, and `launchpad:sync` delivers them, in order, to
 * `launchpad.sync.url`, signed, until the service answers 2xx. The service handles each `event_id` once. Nothing here sends a password.
 */
class Sync
{
    private static bool $muted = false;

    public function enabled(): bool
    {
        return (bool) config('launchpad.modules.sync') && ! self::$muted;
    }

    /** Runs $work without writing sync events: for bringing across rows the other service already has */
    public static function muted(\Closure $work): mixed
    {
        $was = self::$muted;
        self::$muted = true;

        try {
            return $work();
        } finally {
            self::$muted = $was;
        }
    }

    /** @param  array<string, mixed>  $payload */
    public function emit(string $type, array $payload): ?SyncEvent
    {
        if (! $this->enabled()) {
            return null;
        }

        return SyncEvent::create([
            'event_id' => (string) Str::uuid(),
            'type' => $type,
            'payload' => $payload + ['occurred_at' => now()->toIso8601String()],
            // from the app's clock, not the database's default: Postgres rounds a default to the nearest second, which can be a moment ahead
            'available_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    public function userPayload(Model $user): array
    {
        return [
            'id' => (string) $user->getKey(),
            'legacy_id' => $user->legacy_id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            'staff_role' => $user->staff_role,
            'closed' => $user->deletion_pending_at !== null,
        ];
    }

    /** @return array<string, mixed> */
    public function workspacePayload(Workspace $workspace): array
    {
        return ['id' => (string) $workspace->id, 'legacy_id' => $workspace->legacy_id, 'slug' => $workspace->slug, 'name' => $workspace->name, 'kind' => $workspace->kind,
            'owner_id' => (string) $workspace->owner_id, 'timezone' => $workspace->timezone];
    }

    /** @return array<string, mixed> the plan a workspace is on and what it allows, as the service needs it */
    public function planPayload(Workspace $workspace): array
    {
        $entitlements = app(Entitlements::class);
        [$plan, $period] = $entitlements->resolve($workspace);
        $limits = [];
        foreach (array_keys($plan->limits) as $name) {
            $limits[$name] = $entitlements->limit($workspace, $name);
        }

        return ['workspace_id' => (string) $workspace->id, 'plan' => $plan->key, 'period' => $period, 'limits' => $limits, 'features' => $plan->features];
    }

    public function membershipPayload(Membership $membership, bool $removed = false): array
    {
        return ['workspace_id' => (string) $membership->workspace_id, 'user_id' => (string) $membership->user_id, 'role' => $removed ? null : $membership->role->value, 'custom_role' => $removed || ! $membership->custom_role_id ? null : CustomRole::find($membership->custom_role_id)?->name];
    }

    /** The signature the service checks: sha256 over "<timestamp>.<body>" with the shared secret */
    public static function sign(string $timestamp, string $body, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /** Delivers what is due, oldest first. Returns how many were delivered. */
    public function deliver(int $limit = 100): int
    {
        $url = (string) config('launchpad.sync.url');
        $secret = (string) config('launchpad.sync.secret');

        if ($url === '' || strlen($secret) < 32) {
            return 0;
        }

        $delivered = 0;

        // strictly in order: an event waiting for its next try holds back every later one, or the service would hear of them first
        foreach (SyncEvent::where('status', 'pending')->orderBy('id')->limit($limit)->get() as $event) {
            if ($event->available_at->isFuture()) {
                break;
            }

            $body = json_encode(['id' => $event->event_id, 'type' => $event->type, 'data' => $event->payload], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $timestamp = (string) time();

            try {
                $response = Http::timeout((int) config('launchpad.sync.timeout', 5))->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Launchpad-Event-Id' => $event->event_id,
                    'X-Launchpad-Timestamp' => $timestamp,
                    'X-Launchpad-Signature' => self::sign($timestamp, $body, $secret),
                ])->withBody($body, 'application/json')->post($url);

                $ok = $response->successful();
                $error = $ok ? null : 'HTTP '.$response->status();
            } catch (\Throwable $e) {
                $ok = false;
                $error = Str::limit($e->getMessage(), 500, '');
            }

            if ($ok) {
                $event->update(['status' => 'delivered', 'delivered_at' => now(), 'attempts' => $event->attempts + 1, 'last_error' => null]);
                $delivered++;

                continue;
            }

            $attempts = $event->attempts + 1;
            $gaveUp = $attempts >= (int) config('launchpad.sync.max_attempts', 12);
            // 30 s, 1 min, 2 min ... at most an hour
            $event->update(['attempts' => $attempts, 'last_error' => $error, 'status' => $gaveUp ? 'failed' : 'pending', 'available_at' => now()->addSeconds(min(3600, 30 * 2 ** ($attempts - 1)))]);

            // the service is down or refusing: later events would only meet the same answer, and would arrive before this one
            break;
        }

        return $delivered;
    }
}
