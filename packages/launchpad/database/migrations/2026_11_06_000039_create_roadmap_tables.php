<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roadmap_items', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('description')->nullable();
            // review (only its author sees it), idea, planned, building, done, declined, merged
            $table->string('status', 10)->default('review')->index();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('votes')->default(0);
            $table->foreignId('merged_into_id')->nullable()->constrained('roadmap_items')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('roadmap_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roadmap_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['roadmap_item_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_votes');
        Schema::dropIfExists('roadmap_items');
    }
};
