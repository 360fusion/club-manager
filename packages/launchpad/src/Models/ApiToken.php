<?php

namespace Launchpad\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Launchpad\Workspaces\Activity;

/**
 * Sanctum's personal access token, for one workspace: made by a person (its `tokenable`), acting as them, in one workspace. Sanctum does the rest:
 * the hash, the `id|token` form, abilities, expiry and the guard. A token with no workspace is not one of ours (an app's own use of Sanctum).
 */
class ApiToken extends PersonalAccessToken
{
    protected $table = 'personal_access_tokens';

    /**
     * @param  list<string>  $abilities
     * @return array{0: self, 1: string} the stored row and the plain token (which exists nowhere else)
     */
    public static function issue(Workspace $workspace, string $name, array $abilities, Model $by, ?DateTimeInterface $expiresAt = null): array
    {
        return DB::transaction(function () use ($workspace, $name, $abilities, $by, $expiresAt) {
            $new = $by->createToken($name, array_values($abilities), $expiresAt);
            $new->accessToken->forceFill(['workspace_id' => $workspace->id])->save();

            return [$new->accessToken, $new->plainTextToken];
        });
    }

    /**
     * Makes a replacement for $old (the same name, abilities, person and lifetime) and lets $old go on working for $graceMinutes, never longer than
     * it had left. 0 stops it at once.
     *
     * @return array{0: self, 1: string} the new row and its plain token
     *
     * @throws ValidationException when $old has expired, is already being replaced, or its person's account is gone
     */
    public static function rotate(self $old, int $graceMinutes): array
    {
        if ($old->expires_at?->isPast()) {
            throw ValidationException::withMessages(['token' => 'That token has already expired. Make a new one instead.']);
        }

        if ($old->replaced_at !== null) {
            throw ValidationException::withMessages(['token' => 'That token is already being replaced. Revoke it to stop it now.']);
        }

        if ($old->tokenable === null) {
            throw ValidationException::withMessages(['token' => 'The person who made that token no longer has an account. Revoke it and make a new one.']);
        }

        return DB::transaction(function () use ($old, $graceMinutes) {
            $lifetime = $old->expires_at ? $old->expires_at->getTimestamp() - $old->created_at->getTimestamp() : null;
            $new = Activity::silently(fn () => self::issue($old->workspace, $old->name, $old->abilities, $old->tokenable, $lifetime ? now()->addSeconds($lifetime) : null));

            // a second back for "now", so the next call is already refused
            $end = $graceMinutes > 0 ? now()->addMinutes($graceMinutes) : now()->subSecond();
            $old->forceFill(['replaced_at' => now(), 'expires_at' => $old->expires_at && $old->expires_at->lt($end) ? $old->expires_at : $end])->save();

            Activity::record($old->workspace_id, 'api_token.rotated', "rotated the API token “{$old->name}”");

            return $new;
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
