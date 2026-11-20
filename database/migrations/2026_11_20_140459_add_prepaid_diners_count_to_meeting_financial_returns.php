<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diners who paid ahead by bank transfer or card: their meals go on the kitchen bill, but their money is already in the
 * books (matched bank line, card payouts), so the return must not post it again as cash taken on the night.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_financial_returns', function (Blueprint $table) {
            $table->integer('prepaid_diners_count')->default(0)->after('paid_diners_count');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_financial_returns', function (Blueprint $table) {
            $table->dropColumn('prepaid_diners_count');
        });
    }
};
