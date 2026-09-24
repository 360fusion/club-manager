<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lodges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('masonic_hall_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('number', 20)->nullable();
            $table->string('slug')->unique();
            $table->string('status', 20)->default('active');
            $table->text('meets_text')->nullable();
            $table->string('installation_text')->nullable();
            $table->string('website_url')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('club_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->index(['province_id', 'name']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lodges');
    }
};
