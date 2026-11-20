<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Paddle billing was never used and its package is gone; these were its tables (all empty when removed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('paddle_subscription_items');
        Schema::dropIfExists('paddle_subscriptions');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('customers');
    }

    public function down(): void
    {
        // Not recreated: nothing reads them.
    }
};
