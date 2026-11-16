<?php

namespace Launchpad\Accounts;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Launchpad\Models\Setting;

/**
 * How strictly a new account must prove its email address, edited in the admin and read through here: a missing or unknown value
 * always comes out as something usable.
 *
 *  - required: nobody gets past the verify page until the link in the mail has been clicked;
 *  - soft: people get in at once, but need a verified address to invite, post and buy.
 *
 * There is no "off" in production: the one way to switch it off is EMAIL_VERIFICATION=off, honoured only in the local and testing
 * environments. And "required" is only enforced when mail can actually leave the server: a live site whose mailer is "log" or "array"
 * would otherwise lock every new person out behind a link nobody receives, so it behaves as "soft". The same when the database queue
 * the mail waits in is not being worked (a job has sat unreserved for fifteen minutes).
 *
 * One instance is kept per request (a scoped binding), so the gate does not read the setting for every route.
 */
final class SignupSettings
{
    public const DEFAULT = 'required';

    public const MODES = ['required', 'soft'];

    public const QUEUE_STUCK_AFTER_MINUTES = 15;

    private ?string $chosen = null;

    public static function current(): self
    {
        return app(self::class);
    }

    /** What the admin has chosen: required or soft */
    public function chosen(): string
    {
        if ($this->chosen === null) {
            $value = Setting::get('email_verification', self::DEFAULT);

            $this->chosen = is_string($value) && in_array($value, self::MODES, true) ? $value : self::DEFAULT;
        }

        return $this->chosen;
    }

    /** What is enforced right now: required, soft, or off (local and testing only) */
    public function effective(): string
    {
        if (app()->environment(['local', 'testing']) && config('launchpad.verification.mode_override') === 'off') {
            return 'off';
        }

        if ($this->chosen() === 'required' && ! ($this->mailCanLeave() && $this->queueIsWorked())) {
            return 'soft';
        }

        return $this->chosen();
    }

    /** Whether a verification mail can reach a person: always, except on a live site that only logs its mail */
    public function mailCanLeave(): bool
    {
        if (! app()->environment(['production', 'staging'])) {
            return true;
        }

        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    /** Whether the queue the verification mail waits in is being worked (only the database queue can be looked at) */
    public function queueIsWorked(): bool
    {
        if (! app()->environment(['production', 'staging']) || config('queue.default') !== 'database') {
            return true;
        }

        return Cache::remember('launchpad.queue-is-worked', 60, fn () => ! DB::table((string) config('queue.connections.database.table', 'jobs'))
            ->whereNull('reserved_at')
            ->where('available_at', '<=', now()->subMinutes(self::QUEUE_STUCK_AFTER_MINUTES)->getTimestamp())
            ->exists());
    }

    /**
     * Saves the choice. Switching from soft to required gives every unverified account that has no deadline one, so the switch never
     * stops anyone in the middle of their work; saving "required" again changes nothing.
     */
    public function choose(string $mode): void
    {
        $mode = in_array($mode, self::MODES, true) ? $mode : self::DEFAULT;
        $before = $this->chosen();

        Setting::set('email_verification', $mode);
        $this->chosen = $mode;

        if ($mode === 'required' && $before !== 'required') {
            VerificationGrace::stampWhereMissing();
        }
    }
}
