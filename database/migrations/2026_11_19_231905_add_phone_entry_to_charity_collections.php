<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alms and raffle entered on a phone at the meeting (MeetingCollections): money taken by card, who entered it, and the
 * journal entry that put the cash and cheques into the books as money awaiting banking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_acc_charity_collections', function (Blueprint $table) {
            $table->decimal('card_amount', 10, 2)->default(0);
            $table->unsignedBigInteger('entered_by_user_id')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->index(['meeting_id', 'collection_type']);
        });
    }

    public function down(): void
    {
        Schema::table('club_acc_charity_collections', function (Blueprint $table) {
            $table->dropIndex(['meeting_id', 'collection_type']);
            $table->dropColumn(['card_amount', 'entered_by_user_id', 'journal_entry_id']);
        });
    }
};
