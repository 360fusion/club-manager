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
        $shared = DB::table('lodge_sources')->select('url')->groupBy('url')->havingRaw('count(*) > 1');

        DB::table('lodge_sources')->whereIn('url', $shared)->update(['content_hash' => null]);
    }

    public function down(): void
    {
        // The old hashes are not kept; the next check records them again.
    }
};
