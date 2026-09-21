<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every existing member reply becomes a registration with one attendee, so
     * nothing is lost when the new booking pages replace the old pivot. The
     * event_user table is left untouched until the new pages are verified.
     */
    public function up(): void
    {
        DB::table('event_user')->orderBy('id')->each(function ($row) {
            $user = DB::table('users')->where('id', $row->user_id)->first(['name', 'email']);

            if (! $user || DB::table('event_registrations')->where('event_id', $row->event_id)->where('user_id', $row->user_id)->exists()) {
                return;
            }

            $status = match ($row->attendance_status) {
                'attending', 'declined', 'tentative' => $row->attendance_status,
                default => 'tentative',
            };

            $registrationId = DB::table('event_registrations')->insertGetId([
                'event_id' => $row->event_id,
                'user_id' => $row->user_id,
                'contact_name' => $user->name,
                'contact_email' => $user->email,
                'status' => $status,
                'discount_total' => $row->discount_applied ?? 0,
                'payment_status' => $row->payment_status ?: 'unpaid',
                'amount_paid' => $row->amount_paid ?? 0,
                'summons_sent_at' => $row->summons_sent_at,
                'reminder_sent_at' => $row->reminder_sent_at,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);

            DB::table('event_attendees')->insert([
                'registration_id' => $registrationId,
                'user_id' => $row->user_id,
                'name' => $user->name,
                'is_guest' => false,
                'ticket_tier_id' => $row->ticket_tier_id,
                'attending_dining' => (bool) $row->attending_dining,
                'dietary_requirements' => $row->dietary_requirements,
                'legacy_menu' => $row->menu_selections,
                'checked_in_at' => $row->checked_in_at,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        });
    }

    public function down(): void
    {
        // The registrations tables are dropped by their own migration.
    }
};
