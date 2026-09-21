<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_platform_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('stripe_account_id', 64)->unique();
            $table->string('country', 2)->nullable();
            $table->boolean('details_submitted')->default(false);
            $table->boolean('charges_enabled')->default(false);
            $table->boolean('payouts_enabled')->default(false);
            $table->json('requirements')->nullable();
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->decimal('commission_fixed', 10, 2)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->foreignId('terms_accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_platform_accounts');
    }
};
