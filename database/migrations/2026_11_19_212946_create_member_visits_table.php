<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A member's own record of the lodges they have visited: a directory lodge when there is one, or the name they typed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lodge_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lodge_name', 200);
            $table->date('visited_on');
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'visited_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_visits');
    }
};
