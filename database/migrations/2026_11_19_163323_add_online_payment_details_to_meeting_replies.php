<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which online payment (Stripe payment intent or PayPal capture) paid each dining place, so the Secretary can
     * refund a place through the same provider, and the PayPal order a member started.
     */
    public function up(): void
    {
        Schema::table('meeting_rsvps', function (Blueprint $table) {
            $table->string('paypal_order_id')->nullable();
            $table->string('online_provider')->nullable();
            $table->string('online_payment_id')->nullable()->index();
            $table->timestamp('refunded_at')->nullable();
        });

        Schema::table('meeting_rsvp_guests', function (Blueprint $table) {
            $table->string('online_provider')->nullable();
            $table->string('online_payment_id')->nullable()->index();
            $table->timestamp('refunded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('meeting_rsvp_guests', function (Blueprint $table) {
            $table->dropIndex(['online_payment_id']);
            $table->dropColumn(['online_provider', 'online_payment_id', 'refunded_at']);
        });

        Schema::table('meeting_rsvps', function (Blueprint $table) {
            $table->dropIndex(['online_payment_id']);
            $table->dropColumn(['paypal_order_id', 'online_provider', 'online_payment_id', 'refunded_at']);
        });
    }
};
