<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (!Schema::hasColumn('meetings', 'front_page_logo')) {
                $table->string('front_page_logo')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'front_page_title')) {
                $table->string('front_page_title')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'provincial_grand_master')) {
                $table->string('provincial_grand_master')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'deputy_provincial_grand_master')) {
                $table->string('deputy_provincial_grand_master')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'assistant_provincial_grand_masters')) {
                $table->text('assistant_provincial_grand_masters')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'cover_club_name')) {
                $table->string('cover_club_name')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'cover_club_number')) {
                $table->string('cover_club_number')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'cover_motto')) {
                $table->string('cover_motto')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'cover_worshipful_master')) {
                $table->string('cover_worshipful_master')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $cols = array_filter([
                'front_page_logo',
                'front_page_title',
                'provincial_grand_master',
                'deputy_provincial_grand_master',
                'assistant_provincial_grand_masters',
                'cover_club_name',
                'cover_club_number',
                'cover_motto',
                'cover_worshipful_master',
            ], fn($col) => Schema::hasColumn('meetings', $col));
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
