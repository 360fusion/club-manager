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
        if (! Schema::hasTable('club_acc_bank_accounts')) {
            Schema::create('club_acc_bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
                $table->foreignId('account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
                $table->string('bank_name'); // Barclays, Lloyds, Stripe, PayPal, SumUp
                $table->string('account_name'); // Operating Account, Stripe Payouts, etc.
                $table->string('account_type')->default('current'); // current, savings, credit_card, payment_gateway, merchant, cash
                $table->string('account_number')->nullable();
                $table->string('sort_code')->nullable();
                $table->string('currency', 3)->default('GBP');
                $table->decimal('opening_balance', 12, 2)->default(0.00);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('club_acc_bank_imports') && ! Schema::hasColumn('club_acc_bank_imports', 'bank_account_id')) {
            Schema::table('club_acc_bank_imports', function (Blueprint $table) {
                $table->foreignId('bank_account_id')->nullable()->after('club_id')->constrained('club_acc_bank_accounts')->nullOnDelete();
            });
        }

        if (Schema::hasTable('club_acc_bank_transactions') && ! Schema::hasColumn('club_acc_bank_transactions', 'bank_account_id')) {
            Schema::table('club_acc_bank_transactions', function (Blueprint $table) {
                $table->foreignId('bank_account_id')->nullable()->after('club_id')->constrained('club_acc_bank_accounts')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('club_acc_bank_transactions', 'bank_account_id')) {
            Schema::table('club_acc_bank_transactions', function (Blueprint $table) {
                $table->dropForeign(['bank_account_id']);
                $table->dropColumn('bank_account_id');
            });
        }

        if (Schema::hasColumn('club_acc_bank_imports', 'bank_account_id')) {
            Schema::table('club_acc_bank_imports', function (Blueprint $table) {
                $table->dropForeign(['bank_account_id']);
                $table->dropColumn('bank_account_id');
            });
        }

        Schema::dropIfExists('club_acc_bank_accounts');
    }
};
