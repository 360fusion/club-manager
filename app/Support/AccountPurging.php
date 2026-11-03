<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Ends an account without deleting its row. Several tables cascade on the user (donations and invoices, accounting
 * contacts, posts, memberships), so a delete would take a club's financial records and history with it. Instead the sign-in
 * and personal-only data go, the club's own records are unlinked from the person, and the row is blanked.
 *
 * Kept on purpose, and said so on the Data and privacy page: a club's member and contact records (the club decides about
 * those), its accounting and audit trail, event bookings, and lodge claims and corrections (moderation records).
 */
final class AccountPurging
{
    public static function purge(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->notifications()->delete();

            foreach (['sessions', 'club_user', 'lodge_follows', 'newsletter_subscriptions', 'province_admins', 'club_visitor_access', 'club_visit_requests'] as $table) {
                DB::table($table)->where('user_id', $user->id)->delete();
            }

            foreach (['club_acc_members', 'accounting_contacts'] as $table) {
                DB::table($table)->where('user_id', $user->id)->update(['user_id' => null]);
            }

            $user->forceFill([
                'name' => 'Deleted user',
                'email' => "deleted-{$user->id}@deleted.invalid",
                'email_verified_at' => null,
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
                'avatar_url' => null,
                'calendar_token' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'is_super_admin' => false,
                'closure_requested_at' => null,
                'purged_at' => now(),
            ])->save();
        });
    }
}
