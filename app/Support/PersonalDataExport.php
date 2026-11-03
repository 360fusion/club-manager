<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Everything the platform holds about one person, for the download on the Data and privacy page. Never passwords, secrets or token values. */
final class PersonalDataExport
{
    /** @return array<string, mixed> */
    public static function for(User $user): array
    {
        $rows = fn (string $table, string $column = 'user_id', array $except = []) => DB::table($table)->where($column, $user->id)->get()
            ->map(fn ($row) => collect((array) $row)->except($except)->all())->all();

        return [
            'exported_at' => now()->toIso8601String(),
            'account' => $user->only(['id', 'name', 'email', 'email_verified_at', 'avatar_url', 'created_at']) + [
                'two_factor_enabled' => filled($user->two_factor_confirmed_at),
                'closure_requested_at' => $user->closure_requested_at,
            ],
            'club_memberships' => DB::table('club_user')->join('clubs', 'clubs.id', '=', 'club_user.club_id')
                ->where('club_user.user_id', $user->id)
                ->get(['clubs.name as club', 'club_user.role', 'club_user.status', 'club_user.rank', 'club_user.committee_role', 'club_user.member_number',
                    'club_user.phone', 'club_user.emergency_contact', 'club_user.dietary_notes', 'club_user.home_club_name', 'club_user.home_club_number', 'club_user.created_at'])
                ->map(fn ($r) => (array) $r)->all(),
            'club_member_records' => $rows('club_acc_members'),
            'club_contact_records' => $rows('accounting_contacts'),
            'event_bookings' => $rows('event_registrations', 'user_id', ['token_hash', 'stripe_session_id', 'stripe_payment_intent', 'paypal_order_id', 'paypal_capture_id']),
            'email_subscriptions' => $rows('newsletter_subscriptions'),
            'lodges_followed' => $rows('lodge_follows'),
            'visit_requests' => $rows('club_visit_requests'),
            'visitor_access' => $rows('club_visitor_access'),
            'lodge_claims' => $rows('lodge_claims'),
            'lodge_corrections' => $rows('lodge_corrections'),
            'notifications' => $user->notifications()->get(['type', 'data', 'read_at', 'created_at'])->all(),
            'api_tokens' => $user->tokens()->get(['name', 'abilities', 'last_used_at', 'expires_at', 'created_at'])->all(),
        ];
    }
}
