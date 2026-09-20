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
        if (Schema::hasTable('club_acc_charity_collections')) {
            Schema::table('club_acc_charity_collections', function (Blueprint $table) {
                if (! Schema::hasColumn('club_acc_charity_collections', 'is_gift_aid_eligible')) {
                    $table->boolean('is_gift_aid_eligible')->default(true)->after('witnessed_by_member_id');
                }
                if (! Schema::hasColumn('club_acc_charity_collections', 'gift_aid_status')) {
                    $table->string('gift_aid_status')->default('pending')->after('is_gift_aid_eligible'); // pending, claimed, reconciled
                }
                if (! Schema::hasColumn('club_acc_charity_collections', 'gift_aid_amount')) {
                    $table->decimal('gift_aid_amount', 10, 2)->default(0.00)->after('gift_aid_status');
                }
                if (! Schema::hasColumn('club_acc_charity_collections', 'gift_aid_reconciled_at')) {
                    $table->timestamp('gift_aid_reconciled_at')->nullable()->after('gift_aid_amount');
                }
                if (! Schema::hasColumn('club_acc_charity_collections', 'bank_transaction_id')) {
                    $table->foreignId('bank_transaction_id')->nullable()->after('gift_aid_reconciled_at')->constrained('club_acc_bank_transactions')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('club_acc_charity_collections')) {
            Schema::table('club_acc_charity_collections', function (Blueprint $table) {
                $table->dropColumn([
                    'is_gift_aid_eligible',
                    'gift_aid_status',
                    'gift_aid_amount',
                    'gift_aid_reconciled_at',
                    'bank_transaction_id',
                ]);
            });
        }
    }
};
