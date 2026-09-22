<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dining money was only ever recorded as a status plus a free-text reference, so a treasurer
     * could not tell whether a head was settled by bank transfer, online or cash at the door.
     */
    public function up(): void
    {
        Schema::table('meeting_rsvps', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('payment_reference'); // bank, online, cash
            $table->timestamp('paid_at')->nullable()->after('payment_method');
            $table->foreignId('paid_recorded_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('meeting_rsvp_guests', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('payment_method');
            $table->foreignId('paid_recorded_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meeting_rsvps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_recorded_by');
            $table->dropColumn(['payment_method', 'paid_at']);
        });

        Schema::table('meeting_rsvp_guests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_recorded_by');
            $table->dropColumn(['payment_method', 'paid_at']);
        });
    }
};
