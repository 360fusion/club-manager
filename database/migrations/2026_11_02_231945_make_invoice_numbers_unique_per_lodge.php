<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice numbers are numbered lodge by lodge (INV-2026-0001 in every lodge), so they are unique within a lodge,
 * not across the whole platform: with the platform-wide index the second lodge could not raise an invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_invoice_number_unique');
            $table->unique(['club_id', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['club_id', 'invoice_number']);
            $table->unique('invoice_number');
        });
    }
};
