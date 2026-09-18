<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('club_acc_annual_officer_rosters')) {
            Schema::table('club_acc_annual_officer_rosters', function (Blueprint $table) {
                if (! Schema::hasColumn('club_acc_annual_officer_rosters', 'start_year')) {
                    $table->integer('start_year')->nullable()->index();
                    $table->integer('end_year')->nullable()->index();
                    $table->date('start_date')->nullable();
                    $table->date('end_date')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('club_acc_annual_officer_rosters')) {
            Schema::table('club_acc_annual_officer_rosters', function (Blueprint $table) {
                if (Schema::hasColumn('club_acc_annual_officer_rosters', 'start_year')) {
                    $table->dropColumn(['start_year', 'end_year', 'start_date', 'end_date']);
                }
            });
        }
    }
};
