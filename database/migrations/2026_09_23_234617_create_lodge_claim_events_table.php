<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lodge_claim_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lodge_claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('lodge_claim_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lodge_claim_events');
    }
};
