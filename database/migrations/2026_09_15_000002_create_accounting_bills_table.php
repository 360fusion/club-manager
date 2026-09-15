<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('bill_number', 50);
            $table->string('vendor_name');
            $table->string('category')->default('General Expense');
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->enum('status', ['unpaid', 'paid', 'cancelled'])->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'status']);
            $table->index(['club_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_bills');
    }
};
