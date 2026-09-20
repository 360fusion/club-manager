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
        Schema::table('club_types', function (Blueprint $table) {
            if (! Schema::hasColumn('club_types', 'description')) {
                $table->text('description')->nullable()->after('code');
            }
            if (! Schema::hasColumn('club_types', 'website_url')) {
                $table->string('website_url')->nullable()->after('description');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_types', function (Blueprint $table) {
            if (Schema::hasColumn('club_types', 'website_url')) {
                $table->dropColumn('website_url');
            }
            if (Schema::hasColumn('club_types', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
