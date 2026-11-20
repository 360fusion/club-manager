<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tells whoever runs the platform when something breaks: a background job that failed, or the first time an error of a
 * new kind happens. Each kind of alert goes at most once an hour, is sent straight away (not through the queue, which
 * may be the thing that is broken) to OPS_ALERT_EMAILS or else the superadmins, and never itself throws. Off on local
 * and test installs.
 */
final class OpsAlerts
{
    private const QUIET_SECONDS = 3600;

    public static function jobFailed(string $job, Throwable $error): void
    {
        self::send('job:'.$job, "A background job failed: {$job}", "{$job} failed:\n\n".self::describe($error)."\n\nSee the failed jobs with: php artisan queue:failed");
    }

    public static function error(Throwable $error): void
    {
        self::send('error:'.get_class($error).':'.$error->getFile().':'.$error->getLine(), 'An error on '.config('app.url'), self::describe($error));
    }

    private static function send(string $kind, string $subject, string $body): void
    {
        if (app()->environment('local', 'testing') && ! config('app.ops_alerts_always')) {
            return;
        }

        try {
            if (! Cache::add('ops-alert:'.sha1($kind), true, self::QUIET_SECONDS)) {
                return;
            }

            $to = self::recipients();
            if ($to === []) {
                return;
            }

            Mail::raw($body."\n\nThis kind of alert is sent at most once an hour.", fn ($message) => $message->to($to)->subject('['.config('app.name').'] '.$subject));
        } catch (Throwable $alertFailure) {
            Log::warning('Could not send an ops alert', ['subject' => $subject, 'error' => $alertFailure->getMessage()]);
        }
    }

    /**
     * @return list<string>
     */
    public static function recipients(): array
    {
        $configured = array_values(array_filter(array_map('trim', explode(',', (string) config('app.ops_alert_emails')))));

        return $configured !== [] ? $configured : User::where('is_super_admin', true)->pluck('email')->filter()->values()->all();
    }

    private static function describe(Throwable $error): string
    {
        return get_class($error).': '.$error->getMessage()."\nat ".$error->getFile().':'.$error->getLine();
    }
}
