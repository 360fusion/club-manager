<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The signing-in book: who actually came to a meeting. Members are signed in on their reply row (made if they had
     * none), members' guests on theirs, and visitors who walk in without a reply in meeting_walk_ins.
     */
    public function up(): void
    {
        Schema::table('meeting_rsvps', function (Blueprint $table) {
            $table->timestamp('attended_at')->nullable();
            $table->foreignId('attendance_recorded_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('meeting_rsvp_guests', function (Blueprint $table) {
            $table->timestamp('attended_at')->nullable();
        });

        Schema::create('meeting_walk_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->string('name');
            $table->string('rank')->nullable();
            $table->string('lodge')->nullable();
            $table->foreignId('signed_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_walk_ins');

        Schema::table('meeting_rsvp_guests', function (Blueprint $table) {
            $table->dropColumn('attended_at');
        });

        Schema::table('meeting_rsvps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_recorded_by');
            $table->dropColumn('attended_at');
        });
    }
};
