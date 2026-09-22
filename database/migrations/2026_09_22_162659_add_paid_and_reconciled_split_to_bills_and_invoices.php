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
            $table->timestamp('reconciled_at')->nullable()->after('paid_at');
            $table->foreignId('reconciled_bank_transaction_id')->nullable()->after('reconciled_at')
                ->constrained('club_acc_bank_transactions')->nullOnDelete();
            $table->foreignId('paid_by_user_id')->nullable()->after('reconciled_bank_transaction_id')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('reconciled_at')->nullable()->after('paid_at');
            $table->foreignId('reconciled_bank_transaction_id')->nullable()->after('reconciled_at')
                ->constrained('club_acc_bank_transactions')->nullOnDelete();
            $table->foreignId('paid_by_user_id')->nullable()->after('reconciled_bank_transaction_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reconciled_bank_transaction_id');
            $table->dropConstrainedForeignId('paid_by_user_id');
            $table->dropColumn('reconciled_at');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reconciled_bank_transaction_id');
            $table->dropConstrainedForeignId('paid_by_user_id');
            $table->dropColumn('reconciled_at');
        });
    }
};
