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
                if (! Schema::hasColumn('club_acc_bank_accounts', 'gocardless_access_token')) {
                    $table->text('gocardless_access_token')->nullable()->after('sumup_connected_at');
                    $table->string('gocardless_environment')->default('sandbox')->after('gocardless_access_token');
                    $table->text('gocardless_webhook_secret')->nullable()->after('gocardless_environment');
                    $table->timestamp('gocardless_connected_at')->nullable()->after('gocardless_webhook_secret');
                }
            });
        }

        if (! Schema::hasTable('club_acc_direct_debit_mandates')) {
            Schema::create('club_acc_direct_debit_mandates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('membership_id')->nullable()->constrained('memberships')->nullOnDelete();
                $table->string('gocardless_customer_id')->nullable();
                $table->string('gocardless_mandate_id')->unique();
                $table->string('scheme')->default('bacs');
                $table->string('status')->default('pending_submission'); // pending_submission, active, failed, cancelled, expired
                $table->string('bank_name')->nullable();
                $table->string('account_holder_name')->nullable();
                $table->string('account_number_ending')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_acc_direct_debit_mandates');

        if (Schema::hasTable('club_acc_bank_accounts')) {
            Schema::table('club_acc_bank_accounts', function (Blueprint $table) {
                $columns = [
                    'gocardless_access_token',
                    'gocardless_environment',
                    'gocardless_webhook_secret',
                    'gocardless_connected_at',
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
