<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // an unverified claim must not block the domain's real owner: only one team can verify a domain, which the app checks
        Schema::table('sso_connections', function (Blueprint $table) {
            $table->dropUnique(['domain']);
            $table->index('domain');
        });
    }

    public function down(): void
    {
        // the unique index is not put back: unverified claims may repeat a domain by now, and it would fail on them
    }
};
