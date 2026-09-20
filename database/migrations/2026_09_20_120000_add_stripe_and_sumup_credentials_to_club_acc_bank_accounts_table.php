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
        if (Schema::hasTable('club_acc_bank_accounts')) {
            Schema::table('club_acc_bank_accounts', function (Blueprint $table) {
                if (! Schema::hasColumn('club_acc_bank_accounts', 'stripe_secret_key')) {
                    $table->text('stripe_secret_key')->nullable()->after('sync_status');
                    $table->string('stripe_publishable_key')->nullable()->after('stripe_secret_key');
                    $table->timestamp('stripe_connected_at')->nullable()->after('stripe_publishable_key');
                }

                if (! Schema::hasColumn('club_acc_bank_accounts', 'sumup_api_key')) {
                    $table->text('sumup_api_key')->nullable()->after('stripe_connected_at');
                    $table->string('sumup_merchant_code')->nullable()->after('sumup_api_key');
                    $table->timestamp('sumup_connected_at')->nullable()->after('sumup_merchant_code');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('club_acc_bank_accounts')) {
            Schema::table('club_acc_bank_accounts', function (Blueprint $table) {
                $columns = [
                    'stripe_secret_key',
                    'stripe_publishable_key',
                    'stripe_connected_at',
                    'sumup_api_key',
                    'sumup_merchant_code',
                    'sumup_connected_at',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('club_acc_bank_accounts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
