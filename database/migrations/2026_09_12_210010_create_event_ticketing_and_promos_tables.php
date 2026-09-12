<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_ticket_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->integer('max_quantity')->default(0); // 0 = unlimited
            $table->integer('sold_quantity')->default(0);
            $table->timestamps();

            $table->index('event_id');
        });

        Schema::create('event_promos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->string('code');
            $table->string('discount_type')->default('percent'); // percent, fixed
            $table->decimal('discount_amount', 10, 2);
            $table->integer('max_uses')->default(0);
            $table->integer('uses_count')->default(0);
            $table->timestamps();

            $table->unique(['event_id', 'code']);
        });

        Schema::table('event_user', function (Blueprint $table) {
            $table->foreignId('ticket_tier_id')->nullable()->constrained('event_ticket_tiers')->onDelete('set null');
            $table->string('ticket_qr_code')->nullable()->unique();
            $table->decimal('discount_applied', 10, 2)->default(0.00);
        });
    }

    public function down(): void
    {
        Schema::table('event_user', function (Blueprint $table) {
            $table->dropForeign(['ticket_tier_id']);
            $table->dropColumn(['ticket_tier_id', 'ticket_qr_code', 'discount_applied']);
        });

        Schema::dropIfExists('event_promos');
        Schema::dropIfExists('event_ticket_tiers');
    }
};
