<?php

namespace App\Console\Commands;

use App\Domains\ClubAccounting\Services\MemberInvitationService;
use App\Models\Club;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendInvitationRemindersCommand extends Command
{
    protected $signature = 'app:send-invitation-reminders';

    protected $description = 'Send one reminder to people who were invited to set up an account and have not yet answered';

    public function handle(MemberInvitationService $invitations): int
    {
        $sent = 0;

        $clubIds = DB::table('club_user')
            ->whereIn('status', ['pending', 'active'])
            ->whereNotNull('invitation_token')
            ->whereNull('invitation_accepted_at')
            ->whereNull('invitation_reminded_at')
            ->distinct()
            ->pluck('club_id');

        Club::whereIn('id', $clubIds)->orderBy('id')->each(function (Club $club) use ($invitations, &$sent) {
            $reminderDays = $club->inviteReminderDays();

            if ($reminderDays === 0) {
                return;
            }

            $club->users()
                ->wherePivotIn('status', ['pending', 'active'])
                ->wherePivotNotNull('invitation_token')
                ->wherePivotNull('invitation_accepted_at')
                ->wherePivotNull('invitation_reminded_at')
                ->wherePivot('invited_at', '<=', now()->subDays($reminderDays))
                ->wherePivot('invited_at', '>', now()->subDays($club->inviteExpirationDays()))
                ->get()
                ->each(function (User $user) use ($invitations, $club, &$sent) {
                    if ($invitations->remind($club, $user)) {
                        $sent++;
                    }
                });
        });

        $this->info("Sent {$sent} invitation ".($sent === 1 ? 'reminder' : 'reminders').'.');

        return self::SUCCESS;
    }
}
