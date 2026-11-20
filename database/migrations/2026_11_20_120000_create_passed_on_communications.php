<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Passing on summonses and communications from other lodges, Province and Grand Lodge: each lodge's sister and
     * reciprocal lists, what was passed on, one delivery per person who got it, and each person's choices.
     */
    public function up(): void
    {
        Schema::create('club_lodge_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lodge_id')->constrained()->cascadeOnDelete();
            $table->string('relation', 20);
            // A lodge in the hall the Secretary has unticked.
            $table->boolean('excluded')->default(false);
            $table->timestamps();
            $table->unique(['club_id', 'lodge_id', 'relation']);
        });

        Schema::create('passed_on_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('manual');
            // "Passed on by WBro A Smith, Secretary of Lodge X", fixed when it was sent.
            $table->string('passed_on_by');
            $table->string('kind', 20)->nullable();
            $table->string('type', 20);
            $table->foreignId('lodge_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->date('meeting_date')->nullable();
            $table->string('meeting_time', 5)->nullable();
            $table->string('venue')->nullable();
            $table->string('dress_code')->nullable();
            $table->string('dedupe_key')->index();
            $table->index('meeting_id');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('email_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('passed_on_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('passed_on_items')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('club_acc_members')->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->string('kind', 20);
            $table->foreignId('via_club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('lodge_id')->nullable()->constrained()->nullOnDelete();
            $table->string('dedupe_key');
            $table->boolean('bell')->default(false);
            $table->boolean('emailed')->default(false);
            $table->boolean('follower')->default(false);
            $table->string('token', 64)->unique();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
            $table->index(['dedupe_key', 'user_id']);
            $table->index(['dedupe_key', 'member_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('passed_on_prefs')->nullable();
        });
        Schema::table('club_acc_members', function (Blueprint $table) {
            $table->json('passed_on_prefs')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('club_acc_members', function (Blueprint $table) {
            $table->dropColumn('passed_on_prefs');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('passed_on_prefs');
        });
        Schema::dropIfExists('passed_on_deliveries');
        Schema::dropIfExists('passed_on_items');
        Schema::dropIfExists('club_lodge_links');
    }
};
