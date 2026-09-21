<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_attendees', function (Blueprint $table) {
            // Optional: a guest's own address, so they can be sent a confirmation of their place.
            $table->string('email')->nullable();
            $table->timestamp('notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_attendees', fn (Blueprint $table) => $table->dropColumn(['email', 'notified_at']));
    }
};
