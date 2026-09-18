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
        Schema::create('club_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('newsletter_id')->nullable()->constrained('newsletters')->nullOnDelete();
            $table->string('title');
            $table->string('category')->default('general'); // summons, provincial, event_notice, general, charity
            $table->text('summary')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->json('attachments')->nullable(); // [{name, url, size, mime_type}]
            $table->string('status')->default('draft'); // draft, approved, sent, archived
            $table->boolean('is_important')->default(false);
            $table->timestamp('publish_date')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_updates');
    }
};
