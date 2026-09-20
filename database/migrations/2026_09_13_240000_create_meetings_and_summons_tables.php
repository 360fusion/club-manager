<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Recurring Meeting Rules
        Schema::create('recurring_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->onDelete('cascade');
            $table->string('name'); // e.g. "Regular Lodge Meetings"
            $table->string('occurrence'); // 1st, 2nd, 3rd, 4th, last
            $table->string('day_of_week'); // Monday .. Sunday
            $table->json('active_months'); // [1,2,3,4,5,10,11,12]
            $table->time('default_start_time')->default('18:30:00');
            $table->time('default_rehearsal_time')->nullable()->default('17:30:00');
            $table->string('default_venue')->nullable();
            $table->string('default_dress_code')->default('Dark Suit, Craft Regalia');
            $table->timestamps();

            $table->index('club_id');
        });

        // 2. Meetings Table
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->onDelete('cascade');
            $table->foreignId('recurring_rule_id')->nullable()->constrained('recurring_rules')->onDelete('set null');
            $table->integer('meeting_number')->unsigned()->nullable(); // e.g. 452
            $table->string('title'); // e.g. "Installation Meeting"
            $table->date('meeting_date');
            $table->time('starts_at');
            $table->time('rehearsal_starts_at')->nullable();
            $table->string('venue');
            $table->string('dress_code');
            $table->string('status')->default('draft'); // draft, published, completed, cancelled
            $table->timestamp('summons_published_at')->nullable();
            $table->timestamp('rsvp_cutoff_at')->nullable();

            // Festive Board / Dining Details
            $table->string('festive_board_theme')->nullable();
            $table->decimal('dining_cost_member', 10, 2)->default(0.00);
            $table->decimal('dining_cost_guest', 10, 2)->default(0.00);
            $table->string('bank_sort_code')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('payment_reference_prefix')->nullable();

            // Pastoral & Almoner Notes
            $table->text('almoner_notice')->nullable();
            $table->text('sick_distressed_notes')->nullable();
            $table->timestamp('postal_batch_generated_at')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'meeting_date']);
            $table->index(['club_id', 'meeting_date', 'status']);
        });

        // 3. Officer Roles Registry
        Schema::create('officer_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->onDelete('cascade');
            $table->string('title'); // Worshipful Master, Senior Warden, Secretary
            $table->string('short_code'); // WM, SW, SEC
            $table->integer('rank_level')->unsigned()->default(100);
            $table->boolean('is_executive')->default(false);
            $table->timestamps();

            $table->unique(['club_id', 'short_code']);
        });

        // 4. Officer Assignments
        Schema::create('officer_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->onDelete('cascade');
            $table->integer('year')->unsigned(); // e.g. 2026
            $table->foreignId('meeting_id')->nullable()->constrained('meetings')->onDelete('cascade');
            $table->foreignId('officer_role_id')->constrained('officer_roles')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('custom_name')->nullable();
            $table->string('prefix_titles')->nullable(); // W. Bro
            $table->string('suffix_titles')->nullable(); // PPrGSuptWks
            $table->timestamps();

            $table->index(['club_id', 'year', 'meeting_id']);
        });

        // 5. Agenda Items (Order of Business)
        Schema::create('agenda_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->onDelete('cascade');
            $table->integer('item_number')->unsigned();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_ballot')->default(false);
            $table->boolean('is_installation')->default(false);
            $table->foreignId('presenter_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['meeting_id', 'item_number']);
        });

        // 6. Fraternal Visits
        Schema::create('fraternal_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->onDelete('cascade');
            $table->string('visit_type')->default('incoming'); // incoming, outgoing
            $table->string('club_name');
            $table->string('delegation_leader_name');
            $table->integer('guest_count')->unsigned()->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 7. Passwordless RSVPs
        Schema::create('meeting_rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('token_expires_at');
            $table->string('attendance_status')->default('pending'); // attending_dining, attending_meeting_only, apologies, pending
            $table->text('apology_reason')->nullable();
            $table->text('dietary_requirements')->nullable();
            $table->string('payment_status')->default('unpaid'); // unpaid, paid, waived, refunded
            $table->string('payment_reference')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->boolean('is_postal_printed')->default(false);
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
            $table->index(['meeting_id', 'attendance_status']);
        });

        // 8. RSVP Guests
        Schema::create('meeting_rsvp_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_rsvp_id')->constrained('meeting_rsvps')->onDelete('cascade');
            $table->string('guest_name');
            $table->string('guest_title_rank')->nullable();
            $table->string('home_club_lodge')->nullable();
            $table->boolean('attending_dining')->default(true);
            $table->text('dietary_requirements')->nullable();
            $table->decimal('dining_fee', 10, 2)->default(0.00);
            $table->string('payment_status')->default('unpaid');
            $table->timestamps();

            $table->index('meeting_rsvp_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_rsvp_guests');
        Schema::dropIfExists('meeting_rsvps');
        Schema::dropIfExists('fraternal_visits');
        Schema::dropIfExists('agenda_items');
        Schema::dropIfExists('officer_assignments');
        Schema::dropIfExists('officer_roles');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('recurring_rules');
    }
};
