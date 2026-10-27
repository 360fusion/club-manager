<?php

namespace App\Console\Commands;

use App\Enums\ClubMemberStatus;
use App\Enums\ClubRole;
use App\Models\Lodge;
use App\Notifications\LodgeClaimNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Once a year, asks the owners, admins and secretary of each lodge that manages its directory listing whether the
 * listing is still right (bell and email). A lodge is asked a year after it was claimed or last confirmed, and never
 * twice in a year.
 */
class RemindLodgeSecretariesCommand extends Command
{
    protected $signature = 'lodges:remind-secretaries';

    protected $description = 'Ask managed lodges once a year to check their directory listing (and to add a second admin if they have one).';

    public function handle(): int
    {
        $yearAgo = now()->subYear();
        $sent = 0;

        Lodge::query()
            ->whereNotNull('club_id')
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('secretary_reminded_at')->orWhere('secretary_reminded_at', '<=', $yearAgo))
            ->where(fn ($q) => $q->whereNull('last_verified_at')->orWhere('last_verified_at', '<=', $yearAgo))
            ->where(fn ($q) => $q->whereNull('claimed_at')->orWhere('claimed_at', '<=', $yearAgo))
            ->with(['club', 'masonicHall'])
            ->each(function (Lodge $lodge) use (&$sent) {
                $officers = $lodge->club->users()
                    ->wherePivot('status', ClubMemberStatus::Active->value)
                    ->wherePivotIn('role', [ClubRole::Owner->value, ClubRole::Admin->value, ClubRole::Coach->value])
                    ->get();
                $admins = $officers->filter(fn ($user) => in_array($user->pivot->role, [ClubRole::Owner->value, ClubRole::Admin->value], true))->count();

                if ($officers->isNotEmpty()) {
                    Notification::send($officers, LodgeClaimNotification::yearlyCheck($lodge, $admins <= 1));
                    $sent++;
                }

                $lodge->update(['secretary_reminded_at' => now()]);
            });

        $this->info("Asked {$sent} lodge(s) to check their listing.");

        return self::SUCCESS;
    }
}
