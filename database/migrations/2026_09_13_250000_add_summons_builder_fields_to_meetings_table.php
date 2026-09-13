<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (!Schema::hasColumn('meetings', 'salutation')) {
                $table->string('salutation')->nullable()->default('Dear Sir and Brother,');
            }
            if (!Schema::hasColumn('meetings', 'intro_text')) {
                $table->text('intro_text')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'rehearsal_text')) {
                $table->text('rehearsal_text')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'honorary_members_text')) {
                $table->text('honorary_members_text')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'provincial_header_text')) {
                $table->text('provincial_header_text')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'fraternal_visits_text')) {
                $table->text('fraternal_visits_text')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'officers_year_label')) {
                $table->text('officers_year_label')->nullable()->default('OFFICERS FOR 2025-2026');
            }
        });

        Schema::table('clubs', function (Blueprint $table) {
            if (!Schema::hasColumn('clubs', 'default_provincial_header_text')) {
                $table->text('default_provincial_header_text')->nullable();
            }
            if (!Schema::hasColumn('clubs', 'default_honorary_members_text')) {
                $table->text('default_honorary_members_text')->nullable();
            }
            if (!Schema::hasColumn('clubs', 'lodge_number')) {
                $table->string('lodge_number')->nullable()->default('1418');
            }
            if (!Schema::hasColumn('clubs', 'motto')) {
                $table->string('motto')->nullable()->default('Fraternus Amor Maneto');
            }
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $columns = array_filter([
                'salutation',
                'intro_text',
                'rehearsal_text',
                'honorary_members_text',
                'provincial_header_text',
                'fraternal_visits_text',
                'officers_year_label',
            ], fn($col) => Schema::hasColumn('meetings', $col));
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('clubs', function (Blueprint $table) {
            $columns = array_filter([
                'default_provincial_header_text',
                'default_honorary_members_text',
                'lodge_number',
                'motto',
            ], fn($col) => Schema::hasColumn('clubs', $col));
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
