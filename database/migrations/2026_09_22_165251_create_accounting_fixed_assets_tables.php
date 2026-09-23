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
        Schema::create('accounting_fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->default('Equipment');
            $table->date('purchase_date');
            $table->decimal('purchase_cost', 12, 2);
            $table->foreignId('bill_id')->nullable()->constrained('accounting_bills')->nullOnDelete();
            $table->string('depreciation_method')->default('straight_line'); // straight_line, reducing_balance, none
            $table->unsignedSmallInteger('useful_life_years')->default(5);
            $table->decimal('salvage_value', 12, 2)->default(0);
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_proceeds', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'disposal_date']);
        });

        Schema::create('accounting_fixed_asset_depreciation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('accounting_fixed_assets', indexName: 'fixed_asset_depreciation_entries_asset_id_foreign')->cascadeOnDelete();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('period'); // e.g. '2026' for an annual depreciation run
            $table->decimal('amount', 12, 2);
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'fixed_asset_depreciation_entries_journal_entry_id_foreign')->nullOnDelete();
            $table->timestamps();

            $table->unique(['fixed_asset_id', 'period'], 'fixed_asset_depreciation_entries_asset_period_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_fixed_asset_depreciation_entries');
        Schema::dropIfExists('accounting_fixed_assets');
    }
};
