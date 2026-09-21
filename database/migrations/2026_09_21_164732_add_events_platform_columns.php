<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('waitlist_enabled')->default(false);
            $table->timestamp('registration_opens_at')->nullable();
            $table->boolean('allow_public_registration')->default(false);
            $table->unsignedSmallInteger('max_guests_per_booking')->nullable();
            $table->string('booking_fee_type', 10)->default('none');
            $table->decimal('booking_fee_amount', 10, 2)->default(0);
            $table->string('booking_fee_scope', 15)->default('per_booking');
            $table->string('booking_fee_label', 60)->nullable();
            $table->string('price_display', 10)->default('standard');
            $table->text('cancellation_policy')->nullable();
        });

        Schema::table('event_menu_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('allergens')->nullable();
        });

        // Who a ticket price is for: all, member, guest or public.
        Schema::table('event_ticket_tiers', function (Blueprint $table) {
            $table->string('audience', 10)->default('all');
        });
    }

    public function down(): void
    {
        Schema::table('event_ticket_tiers', fn (Blueprint $table) => $table->dropColumn('audience'));
        Schema::table('event_menu_items', fn (Blueprint $table) => $table->dropColumn(['sort_order', 'allergens']));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn([
            'capacity', 'waitlist_enabled', 'registration_opens_at', 'allow_public_registration', 'max_guests_per_booking',
            'booking_fee_type', 'booking_fee_amount', 'booking_fee_scope', 'booking_fee_label', 'price_display', 'cancellation_policy',
        ]));
    }
};
