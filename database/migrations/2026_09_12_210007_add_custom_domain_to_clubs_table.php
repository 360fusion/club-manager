<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->string('custom_domain')->nullable()->unique();
            $table->string('domain_status')->default('pending'); // pending, verified, active
            $table->timestamp('domain_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn(['custom_domain', 'domain_status', 'domain_verified_at']);
        });
    }
};
