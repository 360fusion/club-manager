<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** A person closing their own account: a 30-day wait they can cancel, then AccountPurging */
final class AccountClosure
{
    public const WAIT_DAYS = 30;

    /**
     * What stops this account being closed right now.
     *
     * @return list<string>
     */
    public static function blockers(User $user): array
    {
        $blockers = [];

        if ($user->is_super_admin) {
            $blockers[] = 'This is a platform administrator account. Remove its administrator access first.';
        }

        // A club with no owner left cannot change its own staff or settings, so ownership has to be handed over first.
        $soleOwner = DB::table('club_user as mine')
            ->join('clubs', 'clubs.id', '=', 'mine.club_id')
            ->where('mine.user_id', $user->id)->where('mine.role', 'owner')->where('mine.status', 'active')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('club_user as other')
                ->whereColumn('other.club_id', 'mine.club_id')->where('other.role', 'owner')
                ->where('other.status', 'active')->where('other.user_id', '!=', $user->id))
            ->pluck('clubs.name');

        foreach ($soleOwner as $name) {
            $blockers[] = "You are the only owner of {$name}. Make someone else an owner first.";
        }

        return $blockers;
    }

    public static function request(User $user): void
    {
        $user->forceFill(['closure_requested_at' => now()])->save();
    }

    public static function cancel(User $user): void
    {
        $user->forceFill(['closure_requested_at' => null])->save();
    }

    public static function purgeDate(User $user): ?CarbonInterface
    {
        return $user->closure_requested_at?->copy()->addDays(self::WAIT_DAYS);
    }
}
