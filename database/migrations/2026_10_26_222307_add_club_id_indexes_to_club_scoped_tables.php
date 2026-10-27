<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nearly every page asks for "the rows of this club". Postgres does not index a foreign key by itself, so these
     * tables were scanned in full for every request.
     *
     * @var list<string>
     */
    private const TABLES = [
        'accounting_contacts',
        'accounting_fixed_asset_depreciation_entries',
        'club_acc_bank_accounts',
        'club_acc_bank_imports',
        'club_acc_bank_transactions',
        'club_acc_candidate_events',
        'club_acc_candidates',
        'club_acc_charity_collections',
        'club_acc_charity_grants',
        'club_acc_direct_debit_mandates',
        'club_acc_festival_targets',
        'club_acc_member_subscriptions',
        'club_acc_subscription_tiers',
        'club_updates',
        'lodge_claims',
        'media_versions',
        'meeting_financial_returns',
        'newsletter_deliveries',
        'page_revisions',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'club_id') && ! Schema::hasIndex($table, ['club_id'])) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index('club_id'));
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasIndex($table, ['club_id'])) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex(['club_id']));
            }
        }
    }
};
