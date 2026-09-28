<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * accounting_bills.status is stored as varchar(255) even on Postgres (Laravel's
     * enum() creates a plain string column plus a CHECK constraint), but application
     * code already writes 'draft' into it, which violates that constraint. invoices.status
     * has never had this constraint. SQLite (used by the test suite) doesn't enforce
     * check constraints from ALTER-less schema the same way, so this bug is invisible
     * to tests and only reproduces on Postgres.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE accounting_bills DROP CONSTRAINT IF EXISTS accounting_bills_status_check');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE accounting_bills ADD CONSTRAINT accounting_bills_status_check CHECK (status::text = ANY (ARRAY['unpaid'::character varying, 'paid'::character varying, 'cancelled'::character varying]::text[]))");
        }
    }
};
