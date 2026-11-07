<?php

namespace App\Listeners;

use App\Events\SignatureCompleted;
use App\Support\ClubTeamEvents;

/** A document signed: in the club's activity log and to its webhooks */
class RecordSignatureCompleted
{
    public function handle(SignatureCompleted $event): void
    {
        $request = $event->request;

        ClubTeamEvents::record((int) $request->club_id, 'signature.completed', "recorded the signature of {$request->signer_name} ({$request->purpose})", [
            'signature_request_id' => $request->id, 'purpose' => $request->purpose, 'signer_name' => $request->signer_name,
        ]);
    }
}
