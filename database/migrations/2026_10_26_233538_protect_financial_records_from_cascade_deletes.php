<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Deleting a club or a user used to delete every ledger, invoice, audit and payment row that pointed at them,
     * including the append-only logs. Nothing in the app deletes clubs or users today; this makes sure a future
     * "delete account" button, or a clean-up by hand, is refused instead of erasing the books.
     */
    private const USER_TABLES = ['invoices', 'accounting_contacts', 'accounting_year_audits', 'independent_examiner_reports', 'club_acc_direct_debit_mandates'];

    private const CLUB_TABLES = ['invoices', 'event_payment_log', 'donations', 'donation_contributions'];

    public function up(): void
    {
        $this->swap('CASCADE', 'RESTRICT');
    }

    public function down(): void
    {
        $this->swap('RESTRICT', 'CASCADE');
    }

    /**
     * Foreign keys are only rewritten on PostgreSQL; the in-memory SQLite used by the tests cannot alter them and
     * never deletes clubs or users.
     */
    private function swap(string $from, string $to): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $action = $from === 'CASCADE' ? 'c' : 'r';

        foreach (['clubs' => self::CLUB_TABLES, 'users' => self::USER_TABLES] as $parent => $named) {
            $keys = DB::select(
                "select conrelid::regclass::text as tbl, conname, pg_get_constraintdef(oid) as def
                 from pg_constraint where contype = 'f' and confdeltype = ? and confrelid = ?::regclass",
                [$action, $parent]
            );

            foreach ($keys as $key) {
                $table = trim($key->tbl, '"');

                $isLedger = str_starts_with($table, 'accounting_') || str_starts_with($table, 'club_acc_');

                if (! in_array($table, $named, true) && ! ($parent === 'clubs' && $isLedger)) {
                    continue;
                }

                DB::statement("alter table {$key->tbl} drop constraint \"{$key->conname}\"");
                DB::statement("alter table {$key->tbl} add constraint \"{$key->conname}\" ".str_replace("ON DELETE {$from}", "ON DELETE {$to}", $key->def));
            }
        }
    }
};
