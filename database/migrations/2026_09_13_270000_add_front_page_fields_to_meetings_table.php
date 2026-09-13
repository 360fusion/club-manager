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
            $table->string('front_page_logo')->nullable()->after('payment_link');
            $table->string('front_page_title')->nullable()->after('front_page_logo');
            $table->string('provincial_grand_master')->nullable()->after('front_page_title');
            $table->string('deputy_provincial_grand_master')->nullable()->after('provincial_grand_master');
            $table->text('assistant_provincial_grand_masters')->nullable()->after('deputy_provincial_grand_master');
            $table->string('cover_club_name')->nullable()->after('assistant_provincial_grand_masters');
            $table->string('cover_club_number')->nullable()->after('cover_club_name');
            $table->string('cover_motto')->nullable()->after('cover_club_number');
            $table->string('cover_worshipful_master')->nullable()->after('cover_motto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn([
                'front_page_logo',
                'front_page_title',
                'provincial_grand_master',
                'deputy_provincial_grand_master',
                'assistant_provincial_grand_masters',
                'cover_club_name',
                'cover_club_number',
                'cover_motto',
                'cover_worshipful_master',
            ]);
        });
    }
};
