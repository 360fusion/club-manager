<?php

namespace App\Domains\ClubAccounting\Livewire\Candidates;

use App\Domains\ClubAccounting\Enums\CandidateStage;
use App\Domains\ClubAccounting\Livewire\Candidates\Concerns\HandlesCandidateActions;
use App\Domains\ClubAccounting\Models\Candidate;
use App\Domains\ClubAccounting\Models\CandidateEvent;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Services\CandidateTransitionService;
use App\Models\Club;
use App\Support\ClubAccess;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One candidate on a page of their own: edit every detail, see what is needed next, read the history and add notes,
 * and reject, close, hold or move them on.
 */
class CandidateDetail extends Component
{
    use HandlesCandidateActions;

    #[Locked]
    public string $clubSlug;

    // Details
    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $phone = '';

    public ?string $date_of_birth = null;

    public string $occupation = '';

    public string $address = '';

    public string $postcode = '';

    public string $candidate_type = 'new_candidate';

    public string $grand_lodge_number = '';

    public string $mother_lodge_info = '';

    public string $source = '';

    public string $source_note = '';

    public ?int $owner_user_id = null;

    public string $notes = '';

    // Adding to the history
    public string $note_type = 'note';

    public string $note_summary = '';

    public string $note_body = '';

    public ?string $note_date = null;

    public function mount(string $clubSlug, int $candidateId): void
    {
        $this->clubSlug = $clubSlug;
        $this->candidateId = $candidateId;
        $this->note_date = Carbon::now()->format('Y-m-d');
        $this->fillFromCandidate($this->candidateFor($candidateId));
    }

    private function fillFromCandidate(Candidate $candidate): void
    {
        $this->first_name = $candidate->first_name;
        $this->last_name = $candidate->last_name;
        $this->email = $candidate->email ?? '';
        $this->phone = $candidate->phone ?? '';
        $this->date_of_birth = $candidate->date_of_birth?->format('Y-m-d');
        $this->occupation = $candidate->occupation ?? '';
        $this->address = $candidate->address ?? '';
        $this->postcode = $candidate->postcode ?? '';
        $this->candidate_type = $candidate->candidate_type ?: 'new_candidate';
        $this->grand_lodge_number = $candidate->grand_lodge_number ?? '';
        $this->mother_lodge_info = $candidate->mother_lodge_info ?? '';
        $this->source = $candidate->source ?? '';
        $this->source_note = $candidate->source_note ?? '';
        $this->owner_user_id = $candidate->owner_user_id;
        $this->notes = $candidate->notes ?? '';
    }

    public function saveDetails(CandidateTransitionService $service): void
    {
        $club = $this->getClub();

        $this->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'date_of_birth' => 'nullable|date|before:today',
            'occupation' => 'nullable|string|max:150',
            'address' => 'nullable|string|max:10000',
            'postcode' => 'nullable|string|max:20',
            'candidate_type' => 'required|in:new_candidate,joining_member',
            'grand_lodge_number' => 'nullable|string|max:50',
            'mother_lodge_info' => 'nullable|string|max:255',
            'source' => 'nullable|in:website,member,event,social,other',
            'source_note' => 'nullable|string|max:255',
            'owner_user_id' => ['nullable', Rule::exists('club_user', 'user_id')->where('club_id', $club->id)],
            'notes' => 'nullable|string|max:10000',
        ]);

        $service->updateDetails($this->candidateFor((int) $this->candidateId), [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'date_of_birth' => $this->date_of_birth ?: null,
            'occupation' => $this->occupation ?: null,
            'address' => $this->address ?: null,
            'postcode' => $this->postcode ?: null,
            'candidate_type' => $this->candidate_type,
            'grand_lodge_number' => $this->grand_lodge_number ?: null,
            'mother_lodge_info' => $this->mother_lodge_info ?: null,
            'source' => $this->source ?: null,
            'source_note' => $this->source_note ?: null,
            'owner_user_id' => $this->owner_user_id,
            'notes' => $this->notes ?: null,
        ], auth()->user());

        session()->flash('success', 'Details saved.');
    }

    public function addNote(CandidateTransitionService $service): void
    {
        $this->validate([
            'note_type' => 'required|in:note,call,meeting',
            'note_summary' => 'required|string|max:255',
            'note_body' => 'nullable|string|max:5000',
            'note_date' => 'required|date|before_or_equal:today',
        ], ['note_summary.required' => 'Say briefly what happened.']);

        $service->addNote($this->candidateFor((int) $this->candidateId), auth()->user(), $this->note_type, $this->note_summary, $this->note_body, Carbon::parse($this->note_date)->setTimeFrom(now()));

        $this->reset(['note_summary', 'note_body']);
        $this->note_type = 'note';
        $this->note_date = Carbon::now()->format('Y-m-d');
    }

    /**
     * Only plain notes can be removed; the rest of the history stays.
     */
    public function deleteNote(int $eventId): void
    {
        $event = CandidateEvent::where('club_id', $this->getClub()->id)->where('candidate_id', $this->candidateId)->findOrFail($eventId);

        try {
            $event->delete();
        } catch (\LogicException) {
            session()->flash('error', 'That entry is part of the history and cannot be removed.');
        }
    }

    /**
     * Remove a candidate entered by mistake. Owner and admin only, and never once initiated.
     */
    public function deleteCandidate()
    {
        $club = $this->getClub();
        $candidate = $this->candidateFor((int) $this->candidateId);

        if (! auth()->user()->is_super_admin && ! in_array(ClubAccess::role(auth()->user(), $club), ['owner', 'admin'], true)) {
            session()->flash('error', 'Only an owner or admin can delete a candidate.');

            return null;
        }

        if ($candidate->stage === CandidateStage::Initiated) {
            session()->flash('error', 'An initiated candidate is now a member and cannot be deleted here.');

            return null;
        }

        $candidate->delete();
        session()->flash('success', "Candidate '{$candidate->full_name}' deleted.");

        return $this->redirectRoute('admin.club_acc.candidates.index', ['clubSlug' => $club->slug], navigate: false);
    }

    protected function getClub(): Club
    {
        return Club::where('slug', $this->clubSlug)->firstOrFail();
    }

    public function render()
    {
        $club = $this->getClub();
        $candidate = Candidate::with(['proposer', 'seconder', 'owner', 'convertedMember', 'events.author'])->where('club_id', $club->id)->findOrFail($this->candidateId);
        $next = $candidate->isActive() ? $candidate->stage->next() : null;

        // What still has to be in place before the next step.
        $checks = [];
        if ($candidate->stage === CandidateStage::InterviewPending || $next === CandidateStage::LodgeCommittee) {
            $checks['Proposer and seconder chosen'] = $candidate->proposer_member_id && $candidate->seconder_member_id && $candidate->proposer_member_id !== $candidate->seconder_member_id;
        }
        if ($candidate->stage === CandidateStage::LodgeCommittee) {
            $checks['Committee recommendation recorded'] = $candidate->committee_recommended_at !== null;
            $checks['Form P signed'] = $candidate->form_p_signed_at !== null;
            $checks['Proposed in open lodge (date entered)'] = $candidate->proposed_at !== null;
        }
        if ($candidate->stage === CandidateStage::Proposed) {
            $checks['Ballot result recorded'] = $candidate->ballot_result !== null;
        }

        return view('livewire.candidates.candidate-detail', [
            'club' => $club,
            'candidate' => $candidate,
            'next' => $next,
            'checks' => $checks,
            'activeMembers' => Member::where('club_id', $club->id)->active()->orderBy('last_name')->get(),
            'owners' => $club->users()->wherePivot('status', 'active')->orderBy('users.name')->get(['users.id', 'users.name']),
            'reasons' => $this->outcomeReasons(),
            'canReopen' => auth()->user()->is_super_admin || in_array(ClubAccess::role(auth()->user(), $club), ['owner', 'admin'], true),
        ])->layout('components.layouts.app', [
            'title' => $candidate->full_name.' — '.$club->name,
            'club' => $club,
        ]);
    }
}
