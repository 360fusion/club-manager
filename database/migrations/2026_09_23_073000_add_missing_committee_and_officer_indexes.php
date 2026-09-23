<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // club_acc_committee_attendees_committee_meeting_id_attendance_type_index (71 chars)
        // from the original migration exceeded MySQL's 64-char identifier limit and was
        // silently never created (the migration was already recorded as run by the time
        // the index line was added), so add it here under a short explicit name.
        Schema::table('club_acc_committee_attendees', function (Blueprint $table) {
            $table->index(['committee_meeting_id', 'attendance_type'], 'committee_attendees_meeting_type_index');
        });

        $duplicates = DB::table('club_acc_annual_officer_assignments')
            ->select('roster_id', 'member_id', 'office', DB::raw('count(*) as total'))
            ->groupBy('roster_id', 'member_id', 'office')
            ->having('total', '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add the officer_assignments_roster_member_office unique constraint: '
                .$duplicates->count().' duplicate (roster_id, member_id, office) group(s) already exist. '
                .'Deduplicate club_acc_annual_officer_assignments before re-running this migration.'
            );
        }

        // Same 64-char issue as above: club_acc_annual_officer_assignments_roster_id_member_id_office_unique
        // (69 chars) was silently never created.
        Schema::table('club_acc_annual_officer_assignments', function (Blueprint $table) {
            $table->unique(['roster_id', 'member_id', 'office'], 'officer_assignments_roster_member_office_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_acc_annual_officer_assignments', function (Blueprint $table) {
            $table->dropUnique('officer_assignments_roster_member_office_unique');
        });

        Schema::table('club_acc_committee_attendees', function (Blueprint $table) {
            $table->dropIndex('committee_attendees_meeting_type_index');
        });
    }
};
