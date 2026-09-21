<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every amount so far was displayed in pounds. A club that already has
     * financial records and never chose a currency keeps GBP, rather than
     * silently switching to its country's currency now that lodges choose their own.
     * Clubs with no records yet follow their country.
     */
    public function up(): void
    {
        $tables = ['invoices', 'accounting_bills', 'accounting_journal_entries', 'club_acc_bank_transactions', 'club_acc_charity_collections', 'club_acc_charity_grants', 'club_acc_member_subscriptions'];

        DB::table('clubs')->orderBy('id')->each(function ($club) use ($tables) {
            $settings = json_decode($club->settings ?? '', true) ?: [];

            if (! empty($settings['currency'])) {
                return;
            }

            foreach ($tables as $table) {
                if (DB::table($table)->where('club_id', $club->id)->exists()) {
                    $settings['currency'] = 'GBP';

                    DB::table('clubs')->where('id', $club->id)->update(['settings' => json_encode($settings)]);

                    return;
                }
            }
        });
    }

    public function down(): void
    {
        // Nothing to undo: the stored currency is a valid choice either way.
    }
};
