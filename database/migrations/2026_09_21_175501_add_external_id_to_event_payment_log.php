<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The payment provider's own id (a Stripe payment intent), so the same payment can only be recorded once.
        Schema::table('event_payment_log', function (Blueprint $table) {
            $table->string('external_id', 120)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('event_payment_log', fn (Blueprint $table) => $table->dropColumn('external_id'));
    }
};
