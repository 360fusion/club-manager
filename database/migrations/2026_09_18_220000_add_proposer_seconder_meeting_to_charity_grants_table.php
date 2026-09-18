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
        Schema::table('club_acc_charity_grants', function (Blueprint $table) {
            if (! Schema::hasColumn('club_acc_charity_grants', 'proposer_member_id')) {
                $table->foreignId('proposer_member_id')->nullable()->constrained('club_acc_members')->nullOnDelete();
            }
            if (! Schema::hasColumn('club_acc_charity_grants', 'seconder_member_id')) {
                $table->foreignId('seconder_member_id')->nullable()->constrained('club_acc_members')->nullOnDelete();
            }
            if (! Schema::hasColumn('club_acc_charity_grants', 'committee_meeting_id')) {
                $table->foreignId('committee_meeting_id')->nullable()->constrained('club_acc_committee_meetings')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_acc_charity_grants', function (Blueprint $table) {
            $table->dropForeign(['proposer_member_id']);
            $table->dropForeign(['seconder_member_id']);
            $table->dropForeign(['committee_meeting_id']);
            $table->dropColumn(['proposer_member_id', 'seconder_member_id', 'committee_meeting_id']);
        });
    }
};
