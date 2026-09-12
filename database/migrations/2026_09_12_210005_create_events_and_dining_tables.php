<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->onDelete('cascade');
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_rule')->nullable(); // e.g. WEEKLY
            $table->boolean('requires_payment')->default(false);
            $table->decimal('price', 10, 2)->default(0.00);
            $table->boolean('has_dining')->default(false);
            $table->decimal('dining_price', 10, 2)->default(0.00);
            $table->timestamp('rsvp_deadline')->nullable();
            $table->string('status')->default('upcoming');
            $table->timestamps();

            $table->index(['club_id', 'status']);
            $table->index(['club_id', 'starts_at']);
        });

        Schema::create('event_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->string('category'); // starter, main, dessert, beverage
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_vegetarian')->default(false);
            $table->boolean('is_vegan')->default(false);
            $table->boolean('is_gf')->default(false);
            $table->timestamps();

            $table->index(['event_id', 'category']);
        });

        Schema::create('event_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('attendance_status')->default('pending'); // attending, declined, tentative, pending
            $table->timestamp('summons_sent_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->boolean('attending_dining')->default(false);
            $table->jsonb('menu_selections')->default('{}');
            $table->text('dietary_requirements')->nullable();
            $table->string('payment_status')->default('unpaid'); // unpaid, paid, waived, refunded
            $table->decimal('amount_paid', 10, 2)->default(0.00);
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
            $table->index(['event_id', 'attendance_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_user');
        Schema::dropIfExists('event_menu_items');
        Schema::dropIfExists('events');
    }
};
