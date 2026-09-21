<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One booking. A member's booking is tied to their account; a public guest's is not.
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contact_name');
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 50)->nullable();

            // attending, declined, tentative, waitlisted, cancelled
            $table->string('status', 20)->default('attending');
            $table->string('token_hash', 64)->nullable()->unique();
            $table->text('notes')->nullable();

            // Money. Filled in by the payment phases; a free event leaves these at zero.
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('booking_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->string('payment_status', 20)->default('unpaid');
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('amount_refunded', 10, 2)->default(0);
            $table->string('payment_reference', 60)->nullable()->unique();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('stripe_session_id')->nullable();
            $table->string('stripe_payment_intent')->nullable();

            $table->timestamp('summons_sent_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
            $table->index(['event_id', 'status']);
        });

        // One person on a booking: the booker plus each guest.
        Schema::create('event_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('event_registrations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->boolean('is_guest')->default(false);
            $table->foreignId('ticket_tier_id')->nullable()->constrained('event_ticket_tiers')->nullOnDelete();
            $table->boolean('attending_dining')->default(false);
            $table->foreignId('starter_item_id')->nullable()->constrained('event_menu_items')->nullOnDelete();
            $table->foreignId('main_item_id')->nullable()->constrained('event_menu_items')->nullOnDelete();
            $table->foreignId('dessert_item_id')->nullable()->constrained('event_menu_items')->nullOnDelete();
            $table->text('dietary_requirements')->nullable();
            // Free-text choices made before dishes were linked, kept so nothing is lost.
            $table->json('legacy_menu')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamp('checked_in_at')->nullable();
            $table->string('table_label', 50)->nullable();
            $table->timestamps();

            $table->index('registration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendees');
        Schema::dropIfExists('event_registrations');
    }
};
