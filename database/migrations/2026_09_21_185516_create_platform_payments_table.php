<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->nullable()->constrained('event_registrations')->nullOnDelete();
            $table->string('payment_intent', 80)->unique();
            $table->string('currency', 3);
            $table->decimal('gross', 10, 2);
            $table->decimal('commission', 10, 2);
            $table->decimal('refunded', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['club_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_payments');
    }
};
