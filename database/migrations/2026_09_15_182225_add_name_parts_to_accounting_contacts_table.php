<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_contacts', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('middle_names')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_names');
            $table->string('preferred_name')->nullable()->after('last_name');
            $table->boolean('is_member')->default(false)->after('is_active');
        });

        // Backfill: split existing 'name' into first_name + last_name where possible
        DB::table('accounting_contacts')->get()->each(function ($contact) {
            $parts = explode(' ', trim($contact->name), 2);
            DB::table('accounting_contacts')->where('id', $contact->id)->update([
                'first_name' => $parts[0] ?? $contact->name,
                'last_name' => $parts[1] ?? null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('accounting_contacts', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'middle_names', 'last_name', 'preferred_name', 'is_member']);
        });
    }
};
