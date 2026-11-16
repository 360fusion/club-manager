<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sso_connections', function (Blueprint $table) {
            $table->string('scim_token_hash', 64)->nullable()->unique(); // only the hash: the token is shown once
            $table->timestamp('scim_token_at')->nullable();
        });

        // who a team's directory added, so a person it turned off is still there to turn on again
        Schema::create('scim_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sso_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['sso_connection_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scim_identities');
        Schema::table('sso_connections', function (Blueprint $table) {
            $table->dropUnique(['scim_token_hash']);
            $table->dropColumn(['scim_token_hash', 'scim_token_at']);
        });
    }
};
