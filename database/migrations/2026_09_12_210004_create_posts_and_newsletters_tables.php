<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->onDelete('cascade');
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('cover_image_url')->nullable();
            $table->string('status')->default('published'); // draft, published, archived
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'status']);
        });

        Schema::create('newsletters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->onDelete('cascade');
            $table->string('subject');
            $table->longText('content');
            $table->json('target_roles')->nullable();
            $table->string('status')->default('sent'); // draft, queued, sent
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletters');
        Schema::dropIfExists('posts');
    }
};
