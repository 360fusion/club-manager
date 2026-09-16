<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Committee Meetings
        if (! Schema::hasTable('club_acc_committee_meetings')) {
            Schema::create('club_acc_committee_meetings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
                $table->string('title');
                $table->dateTime('meeting_date');
                $table->string('location')->nullable();
                $table->string('status', 30)->default('scheduled'); // scheduled, in_progress, draft_saved, finalized
                $table->longText('notes_raw')->nullable();
                $table->longText('minutes_final')->nullable();
                $table->foreignId('chair_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('secretary_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('finalized_at')->nullable();
                $table->timestamps();

                $table->index(['club_id', 'meeting_date']);
                $table->index(['club_id', 'status']);
            });
        }

        // 2. Committee Attendees & Apologies Roll-Call
        if (! Schema::hasTable('club_acc_committee_attendees')) {
            Schema::create('club_acc_committee_attendees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('committee_meeting_id')->constrained('club_acc_committee_meetings')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name');
                $table->string('role_title')->nullable();
                $table->string('attendance_type', 30)->default('present'); // present, apology, remote_link
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['committee_meeting_id', 'attendance_type']);
            });
        }

        // 3. Committee Agenda Items
        if (! Schema::hasTable('club_acc_committee_agenda_items')) {
            Schema::create('club_acc_committee_agenda_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('committee_meeting_id')->constrained('club_acc_committee_meetings')->cascadeOnDelete();
                $table->unsignedInteger('order')->default(0);
                $table->string('item_type', 40)->default('general'); // general, candidate_vetting, accounts_audit, hall_affairs, motion
                $table->string('title');
                $table->text('description')->nullable();
                $table->text('discussion_notes')->nullable();
                $table->text('recommendation_text')->nullable();
                $table->boolean('is_approved')->default(false);
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('reference_type')->nullable();
                $table->timestamps();

                $table->index(['committee_meeting_id', 'order']);
                $table->index(['reference_type', 'reference_id']);
            });
        }

        // 4. Committee Delegated Tasks
        if (! Schema::hasTable('club_acc_committee_tasks')) {
            Schema::create('club_acc_committee_tasks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('committee_meeting_id')->constrained('club_acc_committee_meetings')->cascadeOnDelete();
                $table->foreignId('agenda_item_id')->nullable()->constrained('club_acc_committee_agenda_items')->nullOnDelete();
                $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('assigned_to_name')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->date('due_date')->nullable();
                $table->string('status', 30)->default('pending'); // pending, in_progress, completed
                $table->dateTime('completed_at')->nullable();
                $table->timestamps();

                $table->index(['committee_meeting_id', 'status']);
                $table->index(['assigned_to_user_id', 'status']);
            });
        }

        // 5. Notices of Motion (Bridge to Summons Builder)
        if (! Schema::hasTable('club_acc_notices_of_motion')) {
            Schema::create('club_acc_notices_of_motion', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
                $table->foreignId('committee_meeting_id')->nullable()->constrained('club_acc_committee_meetings')->nullOnDelete();
                $table->foreignId('agenda_item_id')->nullable()->constrained('club_acc_committee_agenda_items')->nullOnDelete();
                $table->foreignId('proposer_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('proposer_name')->nullable();
                $table->foreignId('seconder_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('seconder_name')->nullable();
                $table->string('title');
                $table->text('motion_text');
                $table->text('rationale')->nullable();
                $table->foreignId('target_lodge_meeting_id')->nullable()->constrained('meetings')->nullOnDelete();
                $table->string('status', 40)->default('draft_committee'); // draft_committee, approved_for_summons, published_on_summons, ratified, rejected
                $table->dateTime('exported_to_summons_at')->nullable();
                $table->dateTime('ratified_at')->nullable();
                $table->timestamps();

                $table->index(['club_id', 'status']);
                $table->index(['target_lodge_meeting_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('club_acc_notices_of_motion');
        Schema::dropIfExists('club_acc_committee_tasks');
        Schema::dropIfExists('club_acc_committee_agenda_items');
        Schema::dropIfExists('club_acc_committee_attendees');
        Schema::dropIfExists('club_acc_committee_meetings');
    }
};
