<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL does not index foreign keys by itself. These are the columns looked up on their own every page load:
 * a bank account's lines, an account's journal lines, a user's clubs and a user's bookings.
 */
return new class extends Migration
{
    /** Named, because the generated name of the first is longer than MySQL's 64-character limit. */
    private const INDEXES = [
        'club_acc_bank_transactions' => ['bank_txn_account_date_index', ['bank_account_id', 'transaction_date']],
        'accounting_journal_items' => ['journal_items_account_index', ['account_id']],
        'club_user' => ['club_user_user_status_index', ['user_id', 'status']],
        'event_registrations' => ['event_registrations_user_index', ['user_id']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => [$name, $columns]) {
            if (! Schema::hasIndex($table, $columns)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => [$name, $columns]) {
            if (Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
            }
        }
    }
};
