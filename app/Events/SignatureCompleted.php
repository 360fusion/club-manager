<?php

namespace App\Events;

use App\Models\SignatureRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * One signature request has been signed. Listeners react per signable type — the request itself stays ignorant
 * of what a Candidate or an AccountingYearAudit needs to do once all of their signers are done.
 */
class SignatureCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public SignatureRequest $request) {}
}
