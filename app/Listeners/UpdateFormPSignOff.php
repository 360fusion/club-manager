<?php

namespace App\Listeners;

use App\Domains\ClubAccounting\Models\Candidate;
use App\Domains\ClubAccounting\Models\CandidateEvent;
use App\Enums\SignatureRequestStatus;
use App\Events\SignatureCompleted;
use App\Services\Signatures\SignatureRequestService;

/**
 * Once both the proposer and the seconder have signed Form P for a candidate, mark it signed on the candidate
 * itself, so the existing Candidate::isFormPComplete() check keeps working unchanged.
 */
class UpdateFormPSignOff
{
    public function __construct(private readonly SignatureRequestService $signatures) {}

    public function handle(SignatureCompleted $event): void
    {
        $request = $event->request;

        if (! in_array($request->purpose, ['form_p_proposer', 'form_p_seconder'], true)) {
            return;
        }

        $candidate = $request->signable;

        if (! $candidate instanceof Candidate || $candidate->form_p_signed_at !== null) {
            return;
        }

        $requests = $this->signatures->forSignable($candidate)
            ->whereIn('purpose', ['form_p_proposer', 'form_p_seconder']);

        $signed = $requests->where('status', SignatureRequestStatus::Signed);

        if ($signed->pluck('purpose')->unique()->count() < 2) {
            return;
        }

        $candidate->update(['form_p_signed_at' => now()]);

        CandidateEvent::create([
            'candidate_id' => $candidate->id,
            'club_id' => $candidate->club_id,
            'user_id' => null,
            'type' => 'form_p',
            'occurred_at' => now(),
            'summary' => 'Form P electronically signed by both the proposer and the seconder.',
        ]);
    }
}
