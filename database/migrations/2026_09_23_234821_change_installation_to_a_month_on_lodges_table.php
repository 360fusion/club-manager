<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MONTHS = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];

    public function up(): void
    {
        Schema::table('lodges', function (Blueprint $table) {
            $table->unsignedTinyInteger('installation_month')->nullable()->after('meets_text');
        });

        // An installation is only ever a month: it falls on the lodge's usual weekday in that month.
        foreach (DB::table('lodges')->whereNotNull('installation_text')->get(['id', 'installation_text']) as $lodge) {
            $month = self::MONTHS[strtolower(substr(trim($lodge->installation_text), 0, 3))] ?? null;

            if ($month !== null) {
                DB::table('lodges')->where('id', $lodge->id)->update(['installation_month' => $month]);
            }
        }

        Schema::table('lodges', function (Blueprint $table) {
            $table->dropColumn('installation_text');
        });
    }

    public function down(): void
    {
        Schema::table('lodges', function (Blueprint $table) {
            $table->string('installation_text')->nullable()->after('meets_text');
        });

        Schema::table('lodges', function (Blueprint $table) {
            $table->dropColumn('installation_month');
        });
    }
};
