<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('topic', 64)->nullable();
            $table->text('message');
            // new, handled
            $table->string('status', 16)->default('new');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('help_feedback', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 26)->unique();
            $table->string('article');
            // the article's `updated` date when it was answered, so an answer to an old text can be told from one to the new
            $table->string('version')->nullable();
            $table->boolean('helpful');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['article', 'helpful']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_feedback');
        Schema::dropIfExists('contact_messages');
    }
};
