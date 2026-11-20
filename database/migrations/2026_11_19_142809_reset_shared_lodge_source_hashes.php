<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Pages shared by several lodges are now compared per lodge entry (lodges:check-sources), so their stored
     * whole-page hashes are cleared; the next check records each entry afresh instead of flagging every lodge.
     */
    public function up(): void
    {
        // Plucked first: MySQL refuses an update whose subquery reads the same table (error 1093).
        $shared = DB::table('lodge_sources')->groupBy('url')->havingRaw('count(*) > 1')->pluck('url');

        $shared->chunk(500)->each(fn ($urls) => DB::table('lodge_sources')->whereIn('url', $urls)->update(['content_hash' => null]));
    }

    public function down(): void
    {
        // The old hashes are not kept; the next check records them again.
    }
};
