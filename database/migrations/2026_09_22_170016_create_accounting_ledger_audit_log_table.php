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
        Schema::create('accounting_ledger_audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('entity_type', 40); // bill, invoice, journal_entry, fixed_asset, budget
            $table->unsignedBigInteger('entity_id');
            $table->string('action', 30); // created, updated, paid, reconciled, voided, deleted
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('summary');
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['club_id', 'entity_type', 'entity_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_ledger_audit_log');
    }
};
