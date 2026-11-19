<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paying by bank transfer: when the member said they had sent it, and the bank statement line that showed it arrived
 * (set when the Treasurer reconciles it), so the Secretary can tell "says paid" from "seen in the bank".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->timestamp('said_paid_at')->nullable();
            $table->unsignedBigInteger('bank_transaction_id')->nullable()->index();
        });

        Schema::table('meeting_rsvps', function (Blueprint $table) {
            $table->timestamp('said_paid_at')->nullable();
            $table->unsignedBigInteger('bank_transaction_id')->nullable()->index();
        });

        Schema::table('meeting_rsvp_guests', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_transaction_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', fn (Blueprint $table) => $table->dropColumn(['said_paid_at', 'bank_transaction_id']));
        Schema::table('meeting_rsvps', fn (Blueprint $table) => $table->dropColumn(['said_paid_at', 'bank_transaction_id']));
        Schema::table('meeting_rsvp_guests', fn (Blueprint $table) => $table->dropColumn(['bank_transaction_id']));
    }
};
