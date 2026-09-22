<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An audit row is now created as soon as the two auditors are named, before either has actually signed, so
     * signed_off_at stays null until the signature-request flow completes it.
     */
    public function up(): void
    {
        Schema::table('accounting_year_audits', function (Blueprint $table) {
            $table->timestamp('signed_off_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('accounting_year_audits', function (Blueprint $table) {
            $table->timestamp('signed_off_at')->nullable(false)->change();
        });
    }
};
