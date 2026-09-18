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
        Schema::table('club_acc_members', function (Blueprint $table) {
            if (! Schema::hasColumn('club_acc_members', 'county')) {
                $table->string('county')->nullable()->after('city');
            }
            if (! Schema::hasColumn('club_acc_members', 'country')) {
                $table->string('country')->nullable()->after('postcode');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_acc_members', function (Blueprint $table) {
            if (Schema::hasColumn('club_acc_members', 'county')) {
                $table->dropColumn('county');
            }
            if (Schema::hasColumn('club_acc_members', 'country')) {
                $table->dropColumn('country');
            }
        });
    }
};
