<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Short notices an officer sends to a group of members (to their phones, by email or both), kept so the page can list them.
     */
    public function up(): void
    {
        Schema::create('club_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('audience', 32);
            $table->foreignId('meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 100);
            $table->text('body');
            $table->boolean('push')->default(true);
            $table->boolean('email')->default(true);
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('push_count')->default(0);
            $table->unsignedInteger('email_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_notices');
    }
};
