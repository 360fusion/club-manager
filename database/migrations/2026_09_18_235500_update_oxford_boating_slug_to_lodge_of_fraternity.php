<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('clubs')
            ->where('slug', 'oxford-boating')
            ->orWhere('id', 1)
            ->update([
                'name' => 'The Lodge of Fraternity',
                'slug' => 'lodge-of-fraternity',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
