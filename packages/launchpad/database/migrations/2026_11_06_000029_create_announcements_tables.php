<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // short notices staff post for people inside the app (a new feature, planned maintenance)
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('body');
            // info or warning
            $table->string('level', 16)->default('info');
            // everyone, plans (teams on the plans listed) or admins (owners and admins of a team)
            $table->string('audience', 16)->default('everyone');
            $table->json('plans')->nullable();
            $table->boolean('dismissible')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('announcement_dismissals', function (Blueprint $table) {
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_dismissals');
        Schema::dropIfExists('announcements');
    }
};
