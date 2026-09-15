<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_financial_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->onDelete('cascade');
            $table->foreignId('meeting_id')->constrained('meetings')->onDelete('cascade');
            $table->date('return_date');
            $table->decimal('dining_fee_per_head', 10, 2)->default(0.00);
            $table->integer('paid_diners_count')->default(0);
            $table->integer('waived_diners_count')->default(0);
            $table->string('waived_reason')->nullable();
            $table->decimal('kitchen_cost_per_head', 10, 2)->default(0.00);
            $table->string('kitchen_vendor_name')->default('Kitchen Caterer');
            $table->decimal('raffle_amount', 10, 2)->default(0.00);
            $table->decimal('alms_amount', 10, 2)->default(0.00);
            $table->decimal('donations_amount', 10, 2)->default(0.00);
            $table->decimal('bequest_amount', 10, 2)->default(0.00);
            $table->decimal('total_dining_revenue', 10, 2)->default(0.00);
            $table->decimal('total_kitchen_bill', 10, 2)->default(0.00);
            $table->decimal('net_dining_surplus', 10, 2)->default(0.00);
            $table->decimal('total_charity_collected', 10, 2)->default(0.00);
            $table->decimal('net_bank_deposit', 10, 2)->default(0.00);
            $table->foreignId('vendor_bill_id')->nullable()->constrained('accounting_bills')->onDelete('set null');
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_financial_returns');
    }
};
