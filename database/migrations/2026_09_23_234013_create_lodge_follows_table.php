<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lodge_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->boolean('in_calendar')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'lodge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lodge_follows');
    }
};
