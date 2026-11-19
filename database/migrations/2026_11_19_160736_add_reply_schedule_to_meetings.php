<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When each step of a meeting's reply schedule went out (app:send-meeting-replies), so each goes once; and the
     * card payment a member started for their dining, so Stripe's confirmation is recorded once.
     */
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->timestamp('replies_requested_at')->nullable();
            $table->timestamp('last_call_sent_at')->nullable();
            $table->timestamp('numbers_sent_at')->nullable();
            $table->timestamp('confirmations_sent_at')->nullable();
        });

        Schema::table('meeting_rsvps', function (Blueprint $table) {
            $table->string('stripe_session_id')->nullable();
            $table->string('stripe_payment_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('meeting_rsvps', function (Blueprint $table) {
            $table->dropUnique(['stripe_payment_id']);
            $table->dropColumn(['stripe_session_id', 'stripe_payment_id']);
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['replies_requested_at', 'last_call_sent_at', 'numbers_sent_at', 'confirmations_sent_at']);
        });
    }
};
