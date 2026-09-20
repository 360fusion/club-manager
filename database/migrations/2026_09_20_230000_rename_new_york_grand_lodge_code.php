<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Re-code the Grand Lodge of New York onto the `usgl` prefix used by all
     * United States jurisdictions, so the seeder updates it rather than
     * creating a duplicate. Updating in place preserves province links.
     */
    public function up(): void
    {
        DB::table('grand_lodges')
            ->where('code', 'glny')
            ->update(['code' => 'usglny']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('grand_lodges')
            ->where('code', 'usglny')
            ->update(['code' => 'glny']);
    }
};
