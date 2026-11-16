<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lines fetched from Stripe, SumUp, PayPal or GoCardless come from no statement file, so they have no import batch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_acc_bank_transactions', function (Blueprint $table) {
            $table->foreignId('bank_import_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Left nullable: gateway lines already saved have no import batch to point at.
    }
};
