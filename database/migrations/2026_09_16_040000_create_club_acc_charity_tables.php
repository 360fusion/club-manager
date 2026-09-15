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
        Schema::create('club_acc_charity_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->unsignedBigInteger('meeting_id')->nullable();
            $table->string('collection_type')->default('alms_plate');
            $table->decimal('cash_amount', 10, 2)->default(0.00);
            $table->decimal('cheque_amount', 10, 2)->default(0.00);
            $table->foreignId('counted_by_member_id')->nullable()->constrained('club_acc_members')->nullOnDelete();
            $table->foreignId('witnessed_by_member_id')->nullable()->constrained('club_acc_members')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('club_acc_charity_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('recipient_name');
            $table->text('purpose');
            $table->decimal('amount', 10, 2);
            $table->string('relief_chest_number')->nullable();
            $table->string('approval_status')->default('proposed');
            $table->string('bacs_reference')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('club_acc_festival_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('festival_name');
            $table->string('relief_chest_ref');
            $table->decimal('target_amount', 10, 2);
            $table->decimal('bronze_tier', 10, 2);
            $table->decimal('silver_tier', 10, 2);
            $table->decimal('gold_tier', 10, 2);
            $table->decimal('platinum_tier', 10, 2);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('club_acc_member_festival_giving', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('club_acc_members')->cascadeOnDelete();
            $table->decimal('regular_giving_amount', 10, 2)->default(0.00);
            $table->decimal('total_donated_to_date', 10, 2)->default(0.00);
            $table->boolean('qualifies_for_jewel')->default(false);
            $table->boolean('qualifies_for_bar')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_acc_member_festival_giving');
        Schema::dropIfExists('club_acc_festival_targets');
        Schema::dropIfExists('club_acc_charity_grants');
        Schema::dropIfExists('club_acc_charity_collections');
    }
};
