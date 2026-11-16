<?php

namespace Launchpad\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Launchpad\Models\AdminAction;
use Launchpad\Models\AdminLogLabels;

/**
 * The record of what staff did. Rows are written by the admin pages (the RecordsAdminActivity trait), by Setting changes made while a staff
 * member is signed in, and by anything an app wants to leave a trace of. With no staff member signed in nothing is written, so the log is
 * not filled with the app's own housekeeping.
 */
final class AdminLog
{
    public const CHANGED = '(changed)';

    private const MAX_VALUE = 2000;

    /** A field whose name has one of these words in it is a secret: the log says it changed, never to what */
    private const SECRET_WORDS = ['password', 'secret', 'token', 'key', 'apikey', 'credential', 'dsn', 'recovery', 'salt', 'passphrase'];

    private const NOISE = ['updated_at', 'created_at', 'remember_token'];

    /** Adds the words for an app's own action codes */
    public static function describe(array $labels): void
    {
        AdminLogLabels::$extra = array_merge(AdminLogLabels::$extra, $labels);
    }

    /** The staff member acting now: on the admin guard, else the web guard if that person is staff */
    public static function actor(): ?Model
    {
        foreach (['admin', 'web'] as $guard) {
            if (config("auth.guards.{$guard}") === null) {
                continue;
            }

            $user = Auth::guard($guard)->user();

            if ($user && method_exists($user, 'isStaff') && $user->isStaff()) {
                return $user;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function record(string $action, ?Model $subject = null, string $summary = '', array $before = [], array $after = [], ?Model $actor = null): ?AdminAction
    {
        $actor ??= self::actor();

        if ($actor === null) {
            return null;
        }

        return AdminAction::create([
            'actor_id' => $actor->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'summary' => Str::limit($summary, 500, ''),
            'before' => $before === [] ? null : self::clean($before),
            'after' => $after === [] ? null : self::clean($after),
            'ip' => request()?->ip(),
        ]);
    }

    /**
     * A record's change: only the fields that differ are kept, secrets masked. Call it right after a save; without $original it reads the
     * model's own record of what the save changed.
     *
     * @param  array<string, mixed>|null  $original  the attributes from before the change
     */
    public static function saved(Model $model, string $action, ?array $original = null): ?AdminAction
    {
        $before = [];
        $after = [];

        foreach ($original === null ? $model->getChanges() : $model->getAttributes() as $key => $value) {
            $was = $original === null ? ($model->getPrevious()[$key] ?? null) : ($original[$key] ?? null);

            if (in_array($key, self::NOISE, true) || ($original !== null && ! array_key_exists($key, $original)) || $was == $value) {
                continue;
            }

            $before[$key] = $was;
            $after[$key] = $value;
        }

        return self::record($action, $model, class_basename($model).' #'.$model->getKey(), $before, $after);
    }

    public static function settingChanged(string $key, mixed $old, mixed $new): ?AdminAction
    {
        return self::record('setting.changed', null, "Setting {$key}", [$key => $old], [$key => $new]);
    }

    /** @param  array<string, mixed>  $values */
    private static function clean(array $values): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            if (self::isSecret((string) $key)) {
                $out[$key] = self::CHANGED;

                continue;
            }

            $out[$key] = is_scalar($value) || $value === null ? Str::limit((string) $value, self::MAX_VALUE, '...') : Str::limit(json_encode($value), self::MAX_VALUE, '...');
        }

        return $out;
    }

    private static function isSecret(string $key): bool
    {
        $words = preg_split('/[^a-z0-9]+/', Str::snake($key));

        return (bool) array_intersect($words, self::SECRET_WORDS);
    }
}
