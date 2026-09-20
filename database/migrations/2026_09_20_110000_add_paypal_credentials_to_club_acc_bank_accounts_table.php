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
                if (! Schema::hasColumn('club_acc_bank_accounts', 'paypal_client_id')) {
                    $table->string('paypal_client_id')->nullable()->after('opening_balance');
                    $table->text('paypal_client_secret')->nullable()->after('paypal_client_id');
                    $table->string('paypal_environment')->default('live')->after('paypal_client_secret'); // live or sandbox
                    $table->string('paypal_merchant_id')->nullable()->after('paypal_environment');
                    $table->timestamp('paypal_connected_at')->nullable()->after('paypal_merchant_id');
                    $table->timestamp('last_synced_at')->nullable()->after('paypal_connected_at');
                    $table->string('sync_status')->default('idle')->after('last_synced_at'); // idle, connected, error, syncing
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
                    'paypal_client_id',
                    'paypal_client_secret',
                    'paypal_environment',
                    'paypal_merchant_id',
                    'paypal_connected_at',
                    'last_synced_at',
                    'sync_status',
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
