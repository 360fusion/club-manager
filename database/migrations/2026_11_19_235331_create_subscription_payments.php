<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each payment towards a member's subscription, so a member can see what has been paid and when (one lump sum, or a
 * standing order each month). Before this a payment was only a line of note text and a journal entry, so the existing
 * ones are copied in from the journal (source MemberSubscriptionPayment). Also lets a remembered bank payee point at
 * the member who pays that way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_acc_subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->unsignedBigInteger('subscription_id')->index();
            $table->decimal('amount', 10, 2);
            $table->date('paid_on');
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('bank_transaction_id')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();
        });

        Schema::table('club_acc_bank_match_rules', function (Blueprint $table) {
            $table->unsignedBigInteger('member_id')->nullable();
        });

        $entries = DB::table('accounting_journal_entries as entries')
            ->join('accounting_journal_items as items', 'items.journal_entry_id', '=', 'entries.id')
            ->where('entries.source_type', 'MemberSubscriptionPayment')
            ->where('entries.status', '!=', 'void')
            ->groupBy('entries.id', 'entries.club_id', 'entries.source_id', 'entries.entry_date', 'entries.created_at')
            ->selectRaw('entries.id, entries.club_id, entries.source_id, entries.entry_date, entries.created_at, sum(items.debit) as amount')
            ->get();

        foreach ($entries as $entry) {
            DB::table('club_acc_subscription_payments')->insert([
                'club_id' => $entry->club_id,
                'subscription_id' => $entry->source_id,
                'amount' => $entry->amount,
                'paid_on' => substr((string) $entry->entry_date, 0, 10),
                'journal_entry_id' => $entry->id,
                'created_at' => $entry->created_at,
                'updated_at' => $entry->created_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('club_acc_bank_match_rules', fn (Blueprint $table) => $table->dropColumn('member_id'));
        Schema::dropIfExists('club_acc_subscription_payments');
    }
};
