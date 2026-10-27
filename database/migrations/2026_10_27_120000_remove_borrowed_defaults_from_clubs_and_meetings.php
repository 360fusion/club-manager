<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The summons builder migration gave every club the lodge number "1418" and the motto "Fraternus Amor Maneto", and every
     * meeting the heading "OFFICERS FOR 2025-2026", as column defaults, so a lodge that never entered its own printed
     * another lodge's details on its summons. New rows now start empty. Rows that already hold those values are left alone,
     * because the lodge they belong to may really be that lodge: they need checking by hand.
     */
    public function up(): void
    {
        if (Schema::hasColumn('clubs', 'lodge_number')) {
            Schema::table('clubs', fn (Blueprint $table) => $table->string('lodge_number')->nullable()->default(null)->change());
        }

        if (Schema::hasColumn('clubs', 'motto')) {
            Schema::table('clubs', fn (Blueprint $table) => $table->string('motto')->nullable()->default(null)->change());
        }

        if (Schema::hasColumn('meetings', 'officers_year_label')) {
            Schema::table('meetings', fn (Blueprint $table) => $table->string('officers_year_label')->nullable()->default(null)->change());
        }
    }

    public function down(): void
    {
        // The old defaults were the bug; there is nothing to put back.
    }
};
