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
        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $table->string('currency', 3)->default('GBP');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['club_id', 'code']);
            $table->index(['club_id', 'type']);
        });

        Schema::create('accounting_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('reference_number', 50);
            $table->date('entry_date');
            $table->string('description');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->enum('status', ['draft', 'posted', 'void'])->default('posted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['club_id', 'entry_date']);
            $table->index(['club_id', 'reference_number']);
        });

        Schema::create('accounting_journal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('accounting_journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounting_accounts')->cascadeOnDelete();
            $table->decimal('debit', 12, 2)->default(0.00);
            $table->decimal('credit', 12, 2)->default(0.00);
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->index(['journal_entry_id', 'account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_journal_items');
        Schema::dropIfExists('accounting_journal_entries');
        Schema::dropIfExists('accounting_accounts');
    }
};
