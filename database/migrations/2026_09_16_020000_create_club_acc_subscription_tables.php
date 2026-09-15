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
        Schema::create('club_acc_subscription_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('annual_amount', 10, 2);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('club_acc_member_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('club_acc_members')->cascadeOnDelete();
            $table->foreignId('tier_id')->nullable()->constrained('club_acc_subscription_tiers')->nullOnDelete();
            $table->integer('billing_year');
            $table->date('due_date');
            $table->decimal('amount_due', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0.00);
            $table->string('status')->default('unpaid');
            $table->string('invoice_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['member_id', 'billing_year']);
        });

        Schema::table('club_acc_members', function (Blueprint $table) {
            $table->foreignId('subscription_tier_id')->nullable()->after('current_office')->constrained('club_acc_subscription_tiers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_acc_members', function (Blueprint $table) {
            $table->dropForeign(['subscription_tier_id']);
            $table->dropColumn('subscription_tier_id');
        });

        Schema::dropIfExists('club_acc_member_subscriptions');
        Schema::dropIfExists('club_acc_subscription_tiers');
    }
};
