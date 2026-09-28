<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who raised a bill/invoice, needed so approveBill()/approveInvoice() can refuse
     * an approver who is also the person who raised it.
     */
    public function up(): void
    {
        Schema::table('accounting_bills', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('club_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('club_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('accounting_bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
