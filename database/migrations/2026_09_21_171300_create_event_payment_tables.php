<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The lodge's own ways of being paid, set up once.
        Schema::create('club_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            // bank_transfer, card_online, pay_later, cash_on_door
            $table->string('type', 20);
            $table->string('label', 100);
            $table->text('instructions')->nullable();
            // Encrypted JSON: bank details, or Stripe keys.
            $table->text('config')->nullable();

            // A discount or fee applied when someone picks this method (an event can override it).
            $table->string('default_adjustment_kind', 10)->default('none');
            $table->string('default_adjustment_mode', 10)->default('fixed');
            $table->decimal('default_adjustment_amount', 10, 2)->default(0);
            $table->string('default_adjustment_scope', 15)->default('per_person');

            // Pay later: when the money is due.
            $table->unsignedSmallInteger('due_days')->nullable();
            $table->string('due_basis', 20)->default('before_event');

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['club_id', 'is_active']);
        });

        // Which of the lodge's methods an event offers, and any event-specific adjustment.
        Schema::create('event_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('club_payment_methods')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            // null kind = use the method's default.
            $table->string('adjustment_kind', 10)->nullable();
            $table->string('adjustment_mode', 10)->nullable();
            $table->decimal('adjustment_amount', 10, 2)->nullable();
            $table->string('adjustment_scope', 15)->nullable();
            $table->unsignedSmallInteger('due_days')->nullable();
            $table->string('due_basis', 20)->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'payment_method_id']);
        });

        // Every change of a booking's payment status: who, when, how much, why. Never edited or deleted.
        Schema::create('event_payment_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('event_registrations')->cascadeOnDelete();
            $table->string('action', 20);
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('method', 100)->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // admin, stripe_webhook, bank_reconciliation, system
            $table->string('source', 20)->default('admin');
            $table->timestamp('received_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['registration_id', 'created_at']);
        });

        // A payment method chosen at booking, and the price breakdown that was agreed.
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->foreign('payment_method_id')->references('id')->on('club_payment_methods')->nullOnDelete();
            $table->decimal('method_adjustment', 10, 2)->default(0);
            $table->string('promo_code', 40)->nullable();
            $table->decimal('promo_discount', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropColumn(['method_adjustment', 'promo_code', 'promo_discount']);
        });
        Schema::dropIfExists('event_payment_log');
        Schema::dropIfExists('event_payment_methods');
        Schema::dropIfExists('club_payment_methods');
    }
};
