<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set once per Province (shared by every lodge under it), so they live here
     * rather than in each club's settings. Null means "not set", never "free".
     */
    public function up(): void
    {
        Schema::table('provinces', function (Blueprint $table) {
            $table->decimal('per_capita_rate', 8, 2)->nullable();
            $table->decimal('festival_contribution_rate', 8, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('provinces', function (Blueprint $table) {
            $table->dropColumn(['per_capita_rate', 'festival_contribution_rate']);
        });
    }
};
