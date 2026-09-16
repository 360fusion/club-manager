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
        if (! Schema::hasTable('club_acc_bank_imports')) {
            Schema::create('club_acc_bank_imports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
                $table->string('filename');
                $table->string('account_number')->nullable();
                $table->string('sort_code')->nullable();
                $table->foreignId('imported_by_member_id')->nullable()->constrained('club_acc_members')->nullOnDelete();
                $table->integer('total_lines')->default(0);
                $table->decimal('total_amount', 12, 2)->default(0.00);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('club_acc_bank_transactions')) {
            Schema::create('club_acc_bank_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
                $table->foreignId('bank_import_id')->constrained('club_acc_bank_imports')->cascadeOnDelete();
                $table->date('transaction_date');
                $table->text('raw_description');
                $table->string('reference')->nullable();
                $table->decimal('amount', 12, 2);
                $table->decimal('balance_after', 12, 2)->nullable();
                $table->string('transaction_hash', 64)->index();
                $table->string('status')->default('unmatched');
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_acc_bank_transactions');
        Schema::dropIfExists('club_acc_bank_imports');
    }
};
