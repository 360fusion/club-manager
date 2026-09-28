<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_acc_bank_match_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('description_pattern');
            $table->string('match_type', 50);
            $table->string('nominal_code', 20)->nullable();
            $table->unsignedInteger('hit_count')->default(1);
            $table->foreignId('created_from_transaction_id')->nullable()->constrained('club_acc_bank_transactions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['club_id', 'description_pattern', 'match_type'], 'bank_match_rules_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_acc_bank_match_rules');
    }
};
