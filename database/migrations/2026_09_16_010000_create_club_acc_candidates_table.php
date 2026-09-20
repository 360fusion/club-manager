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
        if (! Schema::hasTable('club_acc_candidates')) {
            Schema::create('club_acc_candidates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();

                // Identity & Contact
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->date('date_of_birth')->nullable();
                $table->string('occupation')->nullable();
                $table->text('address')->nullable();
                $table->string('postcode')->nullable();

                // Pipeline Stage
                $table->string('stage')->default('enquiry');

                // Form P & UGLE Statutory Vetting
                $table->foreignId('proposer_member_id')->nullable()->constrained('club_acc_members')->nullOnDelete();
                $table->foreignId('seconder_member_id')->nullable()->constrained('club_acc_members')->nullOnDelete();
                $table->timestamp('form_p_signed_at')->nullable();
                $table->boolean('rule_159_cleared')->default(false);
                $table->date('hermes_clearance_date')->nullable();

                // Statutory Declarations
                $table->boolean('belief_in_supreme_being')->default(false);
                $table->boolean('no_criminal_record')->default(false);
                $table->boolean('no_bankruptcies')->default(false);

                // Notes & Feedback
                $table->json('interview_notes')->nullable();
                $table->text('notes')->nullable();

                // Initiation & Member Bridge
                $table->date('initiation_date')->nullable();
                $table->foreignId('converted_member_id')->nullable()->constrained('club_acc_members')->nullOnDelete();

                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_acc_candidates');
    }
};
