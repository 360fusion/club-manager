<?php

namespace App\Console\Commands;

use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Enums\SubscriptionStatus;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Mail\DuesReminderMail;
use App\Notifications\ClubNotification;
use App\Services\DuesTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Reminds members whose dues are behind, overdue or nearly due, in lodges whose Treasurer has switched reminders on
 * (settings.dues_reminders). Who is chased and how often is DuesTracker::shouldRemind.
 */
class SendDuesRemindersCommand extends Command
{
    protected $signature = 'app:send-dues-reminders';

    protected $description = 'Remind members whose dues are behind, overdue or due within two weeks';

    public function handle(DuesTracker $tracker): int
    {
        $sent = 0;
        $bank = [];

        MemberSubscription::with(['club', 'member.user', 'payments'])
            ->whereIn('status', [SubscriptionStatus::Unpaid->value, SubscriptionStatus::PartiallyPaid->value, SubscriptionStatus::ArrearsWarning->value])
            ->whereHas('member', fn ($member) => $member->where('membership_status', MembershipStatus::Active->value))
            // By id, not by page: each reminder changes reminder_sent_at.
            ->lazyById()
            ->each(function (MemberSubscription $subscription) use ($tracker, &$bank, &$sent) {
                $club = $subscription->club;
                if (! $club || empty($club->settings['dues_reminders'])) {
                    return;
                }

                $dues = $tracker->describe($subscription, $club);
                if (! $tracker->shouldRemind($subscription, $dues['pace']['state'])) {
                    return;
                }

                $member = $subscription->member;
                $email = $member->email ?: $member->user?->email;
                if (! $email && ! $member->user) {
                    return;
                }

                $member->user?->notify(ClubNotification::dues($club, $dues['left'], $dues['pace']['message']));
                if ($email) {
                    $bank[$club->id] ??= $tracker->bankDetails($club);
                    Mail::to($email)->send(new DuesReminderMail($club, $member, $dues, $bank[$club->id]));
                }

                $subscription->forceFill(['reminder_sent_at' => now()])->save();
                $sent++;
            });

        $this->info("Sent {$sent} dues ".($sent === 1 ? 'reminder' : 'reminders').'.');

        return self::SUCCESS;
    }
}
