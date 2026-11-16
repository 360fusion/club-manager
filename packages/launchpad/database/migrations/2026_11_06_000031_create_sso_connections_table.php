<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // a team's link to its company's identity provider (OpenID Connect)
        Schema::create('sso_connections', function (Blueprint $table) {
            $table->id();
            $table->belongsToWorkspace();
            $table->string('issuer', 500);
            $table->string('client_id', 255);
            // encrypted at rest
            $table->text('client_secret');
            // the email domain it signs in, proved by a DNS TXT record before it is used
            $table->string('domain', 255)->unique();
            $table->string('verification_token', 64);
            $table->timestamp('domain_verified_at')->nullable();
            $table->string('default_role', 16)->default('member');
            // when on, people on the domain cannot sign in with a password
            $table->boolean('enforce')->default(false);
            $table->timestamps();

            $table->unique('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_connections');
    }
};
