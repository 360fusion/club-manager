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
        Schema::table('accounting_bills', function (Blueprint $table) {
            $table->decimal('net_amount', 12, 2)->nullable()->after('amount');
            $table->decimal('vat_rate', 5, 2)->nullable()->after('net_amount');
            $table->decimal('vat_amount', 12, 2)->nullable()->after('vat_rate');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('net_amount', 10, 2)->nullable()->after('amount');
            $table->decimal('vat_rate', 5, 2)->nullable()->after('net_amount');
            $table->decimal('vat_amount', 10, 2)->nullable()->after('vat_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_bills', function (Blueprint $table) {
            $table->dropColumn(['net_amount', 'vat_rate', 'vat_amount']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['net_amount', 'vat_rate', 'vat_amount']);
        });
    }
};
