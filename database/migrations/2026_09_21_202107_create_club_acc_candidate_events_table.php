<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_acc_candidate_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('club_acc_candidates')->cascadeOnDelete();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->string('from_stage', 30)->nullable();
            $table->string('to_stage', 30)->nullable();
            $table->timestamp('occurred_at');
            $table->string('summary');
            $table->text('body')->nullable();
            $table->timestamps();

            $table->index(['candidate_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_acc_candidate_events');
    }
};
