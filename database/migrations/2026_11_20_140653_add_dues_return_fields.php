<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * For the annual return of members: the per-member due a Grand Lodge charges (the Province's is already on provinces),
 * and the day a member resigned, was excluded or died, so the return can tell who left during the year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grand_lodges', function (Blueprint $table) {
            $table->decimal('per_capita_rate', 8, 2)->nullable();
        });

        Schema::table('club_acc_members', function (Blueprint $table) {
            $table->date('left_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('grand_lodges', function (Blueprint $table) {
            $table->dropColumn('per_capita_rate');
        });

        Schema::table('club_acc_members', function (Blueprint $table) {
            $table->dropColumn('left_on');
        });
    }
};
