<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Invitation links used to be stored as they were emailed. Keep only their hash, so links already sent still
     * work (the link is hashed again when it is opened) but a copy of the table can no longer accept an invitation.
     */
    public function up(): void
    {
        DB::table('club_user')->whereNotNull('invitation_token')->whereRaw('length(invitation_token) <> 64')->orderBy('id')->each(
            fn ($row) => DB::table('club_user')->where('id', $row->id)->update(['invitation_token' => hash('sha256', $row->invitation_token)])
        );
    }

    public function down(): void
    {
        // A hash cannot be turned back into a link; pending invitations simply need re-sending.
    }
};
