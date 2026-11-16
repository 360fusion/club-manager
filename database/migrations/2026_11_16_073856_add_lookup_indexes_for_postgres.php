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
    private const INDEXES = [
        'club_acc_bank_transactions' => ['bank_account_id', 'transaction_date'],
        'accounting_journal_items' => ['account_id'],
        'club_user' => ['user_id', 'status'],
        'event_registrations' => ['user_id'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            if (! Schema::hasIndex($table, $columns)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            if (Schema::hasIndex($table, $columns)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($columns));
            }
        }
    }
};
