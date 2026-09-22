<?php

namespace App\Domains\ClubAccounting\Livewire\Candidates\Concerns;

use App\Domains\ClubAccounting\Enums\CandidateStage;
use App\Domains\ClubAccounting\Models\Candidate;
use App\Domains\ClubAccounting\Services\CandidateTransitionService;
use App\Models\Club;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Locked;

/**
 * The things you can do to a candidate from the board or from their own page: move on, reject, close, hold, reopen, record
 * the ballot, save Form P and the proposal, and record the initiation. Both pages share it so they behave the same.
 */
trait HandlesCandidateActions
{
    #[Locked]
    public ?int $candidateId = null;

    // Reject, close, hold and reopen share one dialog that asks for a reason.
    public bool $showOutcomeModal = false;

    public string $outcomeType = 'reject';

    public string $outcome_reason = '';

    public string $outcome_note = '';

    public ?string $hold_until = null;

    // Ballot result
    public bool $showBallotModal = false;

    public ?string $ballot_date = null;

    public string $ballot_result = 'elected';

    public string $ballot_note = '';

    // Form P and the proposal
    public bool $showFormPModal = false;

    public ?int $proposer_member_id = null;

    public ?int $seconder_member_id = null;

    public ?string $form_p_signed_at = null;

    public ?string $proposed_at = null;

    public bool $committee_recommended = false;

    public bool $rule_159_cleared = false;

    public ?string $hermes_clearance_date = null;

    public bool $belief_in_supreme_being = false;

    public bool $no_criminal_record = false;

    public bool $no_bankruptcies = false;

    // Initiation
    public bool $showInitiationModal = false;

    public ?string $initiation_date = null;

    abstract protected function getClub(): Club;

    protected function candidateFor(int $id): Candidate
    {
        return Candidate::where('club_id', $this->getClub()->id)->findOrFail($id);
    }

    /**
     * Take the next step, or open whatever dialog that step needs (the ballot, the initiation, Form P).
     */
    public function advance(int $id, CandidateTransitionService $service): void
    {
        $candidate = $this->candidateFor($id);
        $next = $candidate->stage->next();

        if (! $next || ! $candidate->isActive()) {
            session()->flash('error', 'There is no next step for this candidate.');

            return;
        }

        if ($next === CandidateStage::Accepted) {
            $this->openBallot($id);

            return;
        }

        if ($next === CandidateStage::Initiated) {
            $this->openInitiationModal($id);

            return;
        }

        try {
            $service->transitionStage($candidate, $next, [], auth()->user());
            session()->flash('success', "{$candidate->full_name} moved to ".$next->label().'.');
        } catch (InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            if (in_array($next, [CandidateStage::LodgeCommittee, CandidateStage::Proposed], true)) {
                $this->openFormPModal($id);
            }
        }
    }

    public function openOutcome(int $id, string $type): void
    {
        abort_unless(in_array($type, ['reject', 'close', 'hold', 'reopen'], true), 404);

        $this->candidateFor($id);
        $this->candidateId = $id;
        $this->outcomeType = $type;
        $this->reset(['outcome_reason', 'outcome_note', 'hold_until']);
        $this->resetErrorBag();
        $this->showOutcomeModal = true;
    }

    public function saveOutcome(CandidateTransitionService $service): void
    {
        $candidate = $this->candidateFor((int) $this->candidateId);

        $this->validate([
            'outcome_reason' => in_array($this->outcomeType, ['reject', 'close'], true) ? ['required', Rule::in(array_keys($this->outcomeType === 'reject' ? CandidateTransitionService::REJECT_REASONS : CandidateTransitionService::CLOSE_REASONS))] : 'nullable',
            'outcome_note' => ['required', 'string', 'min:3', 'max:2000'],
            'hold_until' => 'nullable|date|after:today',
        ], ['outcome_reason.required' => 'Choose a reason from the list.', 'outcome_note.required' => 'Add a short note explaining why.', 'outcome_note.min' => 'Add a short note explaining why.']);

        try {
            match ($this->outcomeType) {
                'reject' => $service->reject($candidate, auth()->user(), $this->outcome_reason, $this->outcome_note),
                'close' => $service->close($candidate, auth()->user(), $this->outcome_reason, $this->outcome_note),
                'hold' => $service->hold($candidate, auth()->user(), $this->hold_until ? Carbon::parse($this->hold_until) : null, $this->outcome_note),
                'reopen' => $service->reopen($candidate, auth()->user(), $this->outcome_note),
            };
        } catch (InvalidArgumentException $e) {
            $this->addError('outcome_note', $e->getMessage());

            return;
        }

        $done = ['reject' => 'rejected', 'close' => 'closed', 'hold' => 'put on hold', 'reopen' => 'reopened'][$this->outcomeType];
        session()->flash('success', "{$candidate->full_name} {$done}.");
        $this->showOutcomeModal = false;
    }

    public function resume(int $id, CandidateTransitionService $service): void
    {
        $candidate = $this->candidateFor($id);
        $service->resume($candidate, auth()->user());
        session()->flash('success', "{$candidate->full_name} is off hold.");
    }

    public function openBallot(int $id): void
    {
        $candidate = $this->candidateFor($id);
        $this->candidateId = $candidate->id;
        $this->ballot_date = Carbon::now()->format('Y-m-d');
        $this->ballot_result = 'elected';
        $this->ballot_note = '';
        $this->resetErrorBag();
        $this->showBallotModal = true;
    }

    public function saveBallot(CandidateTransitionService $service): void
    {
        $candidate = $this->candidateFor((int) $this->candidateId);

        $this->validate(['ballot_date' => 'required|date', 'ballot_result' => 'required|in:elected,not_elected', 'ballot_note' => 'nullable|string|max:2000']);

        try {
            $updated = $service->recordBallot($candidate, auth()->user(), Carbon::parse($this->ballot_date), $this->ballot_result, $this->ballot_note);
        } catch (InvalidArgumentException $e) {
            $this->addError('ballot_date', $e->getMessage());

            return;
        }

        session()->flash('success', $updated->stage === CandidateStage::Accepted
            ? "{$candidate->full_name} was elected. To be initiated by ".$updated->initiate_by->format('j M Y').'.'
            : "{$candidate->full_name} was not elected and has been rejected.");
        $this->showBallotModal = false;
    }

    public function openFormPModal(int $id): void
    {
        $candidate = $this->candidateFor($id);

        $this->candidateId = $candidate->id;
        $this->proposer_member_id = $candidate->proposer_member_id;
        $this->seconder_member_id = $candidate->seconder_member_id;
        $this->form_p_signed_at = $candidate->form_p_signed_at?->format('Y-m-d');
        $this->proposed_at = $candidate->proposed_at?->format('Y-m-d');
        $this->committee_recommended = $candidate->committee_recommended_at !== null;
        $this->rule_159_cleared = (bool) $candidate->rule_159_cleared;
        $this->hermes_clearance_date = $candidate->hermes_clearance_date?->format('Y-m-d');
        $this->belief_in_supreme_being = (bool) $candidate->belief_in_supreme_being;
        $this->no_criminal_record = (bool) $candidate->no_criminal_record;
        $this->no_bankruptcies = (bool) $candidate->no_bankruptcies;
        $this->resetErrorBag();
        $this->showFormPModal = true;
    }

    public function saveFormPVetting(CandidateTransitionService $service): void
    {
        $club = $this->getClub();
        $candidate = $this->candidateFor((int) $this->candidateId);
        $member = Rule::exists('club_acc_members', 'id')->where('club_id', $club->id);

        $this->validate([
            'proposer_member_id' => ['nullable', $member],
            'seconder_member_id' => ['nullable', $member, 'different:proposer_member_id'],
            'form_p_signed_at' => 'nullable|date',
            'proposed_at' => 'nullable|date',
            'hermes_clearance_date' => 'nullable|date',
        ], ['seconder_member_id.different' => 'The seconder must be a different member from the proposer.']);

        $service->updateFormPVetting($candidate, [
            'proposer_member_id' => $this->proposer_member_id,
            'seconder_member_id' => $this->seconder_member_id,
            'form_p_signed_at' => $this->form_p_signed_at ?: null,
            'proposed_at' => $this->proposed_at ?: null,
            'belief_in_supreme_being' => $this->belief_in_supreme_being,
            'no_criminal_record' => $this->no_criminal_record,
            'no_bankruptcies' => $this->no_bankruptcies,
            'rule_159_cleared' => $this->rule_159_cleared,
            'hermes_clearance_date' => $this->hermes_clearance_date ?: null,
        ], auth()->user());

        if ($this->committee_recommended !== ($candidate->committee_recommended_at !== null)) {
            $service->recordCommitteeDecision($candidate->fresh(), auth()->user(), $this->committee_recommended, 'Recorded by hand on the candidate.');
        }

        session()->flash('success', "Form P and proposal details saved for {$candidate->full_name}.");
        $this->showFormPModal = false;
    }

    public function openInitiationModal(int $id): void
    {
        $candidate = $this->candidateFor($id);

        $this->candidateId = $candidate->id;
        $this->initiation_date = $candidate->initiation_date?->format('Y-m-d') ?? Carbon::now()->format('Y-m-d');
        $this->resetErrorBag();
        $this->showInitiationModal = true;
    }

    public function confirmInitiation(CandidateTransitionService $service): void
    {
        $candidate = $this->candidateFor((int) $this->candidateId);

        $this->validate(['initiation_date' => 'required|date']);

        try {
            $service->transitionStage($candidate, CandidateStage::Initiated, ['initiation_date' => $this->initiation_date], auth()->user(), $candidate->stage === CandidateStage::Accepted ? null : 'Initiated without the usual steps recorded');
        } catch (InvalidArgumentException $e) {
            $this->addError('initiation_date', $e->getMessage());

            return;
        }

        session()->flash('success', "{$candidate->full_name} initiated. A member record has been created with the rank Bro.");
        $this->showInitiationModal = false;
    }

    /**
     * @return array<string, string>
     */
    protected function outcomeReasons(): array
    {
        return $this->outcomeType === 'reject' ? CandidateTransitionService::REJECT_REASONS : CandidateTransitionService::CLOSE_REASONS;
    }
}
