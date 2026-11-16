<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->timestamp('invited_at')->nullable(); // set when staff let them in: from then they may sign up
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
