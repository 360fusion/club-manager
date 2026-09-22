<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A meeting's `cover_club_name` is only ever written by the summons form, which pre-fills it
     * with the club's name at the time. A club that later renamed itself was therefore left with
     * old summonses still printing the previous name. Clearing the stale snapshots makes the PDF
     * fall back to the live club name; from here on Club::updated keeps them in step.
     */
    public function up(): void
    {
        DB::table('meetings')
            ->join('clubs', 'clubs.id', '=', 'meetings.club_id')
            ->whereNotNull('meetings.cover_club_name')
            ->whereColumn('meetings.cover_club_name', '!=', 'clubs.name')
            ->update(['meetings.cover_club_name' => null]);
    }

    public function down(): void
    {
        // The previous names are not recoverable, and the fallback renders the correct name anyway.
    }
};
