<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('salutation')->nullable()->default('Dear Sir and Brother,');
            $table->text('intro_text')->nullable();
            $table->text('rehearsal_text')->nullable();
            $table->text('honorary_members_text')->nullable();
            $table->text('provincial_header_text')->nullable();
            $table->text('fraternal_visits_text')->nullable();
            $table->text('officers_year_label')->nullable()->default('OFFICERS FOR 2025-2026');
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->text('default_provincial_header_text')->nullable();
            $table->text('default_honorary_members_text')->nullable();
            $table->string('lodge_number')->nullable()->default('1418');
            $table->string('motto')->nullable()->default('Fraternus Amor Maneto');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn([
                'salutation',
                'intro_text',
                'rehearsal_text',
                'honorary_members_text',
                'provincial_header_text',
                'fraternal_visits_text',
                'officers_year_label',
            ]);
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn([
                'default_provincial_header_text',
                'default_honorary_members_text',
                'lodge_number',
                'motto',
            ]);
        });
    }
};
