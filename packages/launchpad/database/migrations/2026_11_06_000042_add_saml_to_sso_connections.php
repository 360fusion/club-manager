<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sso_connections', function (Blueprint $table) {
            $table->string('protocol', 8)->default('oidc'); // oidc or saml; for saml, issuer is the provider's entity ID
            $table->string('saml_sso_url', 500)->nullable();
            $table->text('saml_certificate')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sso_connections', fn (Blueprint $table) => $table->dropColumn(['protocol', 'saml_sso_url', 'saml_certificate']));
    }
};
