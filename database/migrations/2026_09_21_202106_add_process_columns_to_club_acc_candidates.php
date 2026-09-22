<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_acc_candidates', function (Blueprint $table) {
            $table->string('source', 30)->nullable();
            $table->string('source_note')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('stage_entered_at')->nullable();

            $table->timestamp('on_hold_at')->nullable();
            $table->date('on_hold_until')->nullable();

            // How and why a candidate left the process, and where they were, so a reopen goes back to the right place.
            $table->string('outcome', 20)->nullable();
            $table->string('outcome_reason', 40)->nullable();
            $table->text('outcome_note')->nullable();
            $table->timestamp('outcome_at')->nullable();
            $table->foreignId('outcome_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('outcome_from_stage', 30)->nullable();

            $table->timestamp('committee_recommended_at')->nullable();
            $table->date('proposed_at')->nullable();
            // Only the result of the ballot is kept, never who voted or how.
            $table->date('ballot_at')->nullable();
            $table->string('ballot_result', 20)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->date('initiate_by')->nullable();
        });

        // The old "ballot approved" stage meant the committee had recommended the candidate.
        DB::table('club_acc_candidates')->where('stage', 'ballot_approved')->update(['stage' => 'proposed', 'committee_recommended_at' => DB::raw('updated_at')]);
        DB::table('club_acc_candidates')->update(['stage_entered_at' => DB::raw('updated_at')]);
        DB::table('club_acc_candidates')->where('stage', 'rejected')->update(['outcome' => 'rejected', 'outcome_at' => DB::raw('updated_at')]);
        DB::table('club_acc_candidates')->where('stage', 'withdrawn')->update(['outcome' => 'closed', 'outcome_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        DB::table('club_acc_candidates')->where('stage', 'proposed')->update(['stage' => 'ballot_approved']);
        DB::table('club_acc_candidates')->whereIn('stage', ['first_interview', 'accepted'])->update(['stage' => 'enquiry']);

        Schema::table('club_acc_candidates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropConstrainedForeignId('outcome_by');
            $table->dropColumn(['source', 'source_note', 'stage_entered_at', 'on_hold_at', 'on_hold_until', 'outcome', 'outcome_reason', 'outcome_note', 'outcome_at', 'outcome_from_stage', 'committee_recommended_at', 'proposed_at', 'ballot_at', 'ballot_result', 'accepted_at', 'initiate_by']);
        });
    }
};
