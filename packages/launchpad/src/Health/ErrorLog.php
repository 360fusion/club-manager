<?php

namespace Launchpad\Health;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Launchpad\Models\LoggedError;
use Throwable;

/**
 * Keeps each reported error in `logged_errors` for HQ's Errors page, as well as Laravel's own log. Registered as a reportable callback, so
 * what Laravel does not report (validation, 404s, a lost sign-in) never arrives. One row per class, file and line; a resolved one that
 * happens again is opened again.
 */
final class ErrorLog
{
    private static ?bool $ready = null;

    private static bool $recording = false;

    public static function record(Throwable $e): void
    {
        // an error while recording (the database is the thing that is down, say) must not report itself in a loop
        if (self::$recording) {
            return;
        }

        self::$recording = true;

        try {
            self::$ready ??= Schema::hasTable('logged_errors');

            if (self::$ready) {
                self::write($e);
            }
        } catch (Throwable) {
            // the error still reaches Laravel's log; this page is a convenience
        } finally {
            self::$recording = false;
        }
    }

    /** For tests that make the table after the first error */
    public static function forget(): void
    {
        self::$ready = null;
    }

    private static function write(Throwable $e): void
    {
        $file = Str::after($e->getFile(), base_path().DIRECTORY_SEPARATOR);
        $fingerprint = hash('sha256', get_class($e).'|'.$file.'|'.$e->getLine());
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
        $now = now();

        $seen = [
            'message' => Str::limit($e->getMessage(), 2000, ''), 'url' => $request ? Str::limit($request->fullUrl(), 2000, '') : null,
            'method' => $request?->method(), 'user_id' => Auth::id(), 'trace' => Str::limit($e->getTraceAsString(), 10000, ''), 'last_seen_at' => $now, 'resolved_at' => null,
        ];

        $updated = LoggedError::where('fingerprint', $fingerprint)->update([...$seen, 'occurrences' => DB::raw('occurrences + 1')]);

        if ($updated === 0) {
            LoggedError::insertOrIgnore([...$seen, 'fingerprint' => $fingerprint, 'class' => get_class($e), 'file' => Str::limit($file, 250, ''),
                'line' => $e->getLine(), 'occurrences' => 1, 'first_seen_at' => $now]);
        }
    }
}
