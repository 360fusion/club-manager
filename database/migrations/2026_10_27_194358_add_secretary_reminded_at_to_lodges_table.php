<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the managing lodge's officers were last asked to check the directory listing (once a year).
     */
    public function up(): void
    {
        Schema::table('lodges', function (Blueprint $table) {
            $table->timestamp('secretary_reminded_at')->nullable()->after('last_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('lodges', function (Blueprint $table) {
            $table->dropColumn('secretary_reminded_at');
        });
    }
};
