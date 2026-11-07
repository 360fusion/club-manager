<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->belongsToWorkspace();
            $table->string('url', 2048);
            // the signing secret, encrypted (the app needs it to sign each delivery)
            $table->text('secret');
            $table->json('events');
            $table->boolean('active')->default(true);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();
            // the id the receiver sees; the same for every retry, so it can ignore a repeat
            $table->uuid('uuid')->unique();
            $table->string('event', 64);
            $table->json('payload');
            // pending, delivered or failed
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['endpoint_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');

    }
};
