<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Face ID and fingerprint sign-in in the app: each phone that turns it on gets a long random key, kept in the phone's
 * secure storage behind its biometrics. Only a hash of the key is stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_sign_in_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key_hash', 64)->unique();
            $table->string('platform', 20);
            $table->string('name', 100)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_sign_in_keys');
    }
};
