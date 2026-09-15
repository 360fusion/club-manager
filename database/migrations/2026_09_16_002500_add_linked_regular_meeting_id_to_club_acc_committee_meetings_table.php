<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_acc_committee_meetings', function (Blueprint $table) {
            $table->foreignId('linked_regular_meeting_id')->nullable()->after('club_id')->constrained('meetings')->nullOnDelete();
            $table->string('time_opened', 10)->default('19:00')->nullable()->after('meeting_date');
        });
    }

    public function down(): void
    {
        Schema::table('club_acc_committee_meetings', function (Blueprint $table) {
            $table->dropForeign(['linked_regular_meeting_id']);
            $table->dropColumn(['linked_regular_meeting_id', 'time_opened']);
        });
    }
};
