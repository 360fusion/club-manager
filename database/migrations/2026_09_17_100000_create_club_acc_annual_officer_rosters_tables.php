<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('club_acc_annual_officer_rosters')) {
            Schema::create('club_acc_annual_officer_rosters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
                $table->foreignId('meeting_id')->nullable()->constrained('meetings')->nullOnDelete();
                $table->string('masonic_year', 20); // e.g. "2026-2027"
                $table->string('status', 30)->default('draft'); // draft, proposed, confirmed, installed
                $table->timestamp('confirmed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['club_id', 'masonic_year']);
            });
        }

        if (! Schema::hasTable('club_acc_annual_officer_assignments')) {
            Schema::create('club_acc_annual_officer_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('roster_id')->constrained('club_acc_annual_officer_rosters')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('club_acc_members')->cascadeOnDelete();
                $table->string('office', 50); // sw, jw, dc, charity_steward, etc.
                $table->string('category', 30); // progressive vs administrative
                $table->timestamps();

                $table->unique(['roster_id', 'member_id', 'office']);
            });
        }

        if (Schema::hasTable('agenda_items') && ! Schema::hasColumn('agenda_items', 'is_officer_election')) {
            Schema::table('agenda_items', function (Blueprint $table) {
                $table->boolean('is_officer_election')->default(false);
                $table->string('masonic_year', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('agenda_items') && Schema::hasColumn('agenda_items', 'is_officer_election')) {
            Schema::table('agenda_items', function (Blueprint $table) {
                $table->dropColumn(['is_officer_election', 'masonic_year']);
            });
        }
        Schema::dropIfExists('club_acc_annual_officer_assignments');
        Schema::dropIfExists('club_acc_annual_officer_rosters');
    }
};
