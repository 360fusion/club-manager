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
        Schema::create('accounting_year_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->unsignedSmallInteger('financial_year');
            $table->foreignId('auditor_one_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('auditor_two_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('signed_off_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'financial_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_year_audits');
    }
};
