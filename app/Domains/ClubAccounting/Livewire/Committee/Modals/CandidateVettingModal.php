<?php

namespace App\Domains\ClubAccounting\Livewire\Committee\Modals;

use App\Domains\ClubAccounting\Enums\CommitteeItemType;
use App\Domains\ClubAccounting\Models\Candidate;
use App\Domains\ClubAccounting\Models\ClubCommitteeAgendaItem;
use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Domains\ClubAccounting\Services\CandidateTransitionService;
use App\Models\User;
use App\Support\ClubAccess;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CandidateVettingModal extends Component
{
    #[Locked]
    public int $meetingId;

    #[Locked]
    public ?int $candidateId = null;

    public bool $isOpen = false;

    public string $vettingNotes = '';

    public bool $isRecommended = true;

    protected $listeners = ['openCandidateVetting' => 'loadCandidate'];

    public function loadCandidate(int $candidateId, int $meetingId): void
    {
        $this->candidateId = $candidateId;
        $this->meetingId = $meetingId;
        $this->isOpen = true;
        $this->vettingNotes = 'Candidate vetted under UGLE Rule 159. Identity verified, age qualification and proposer/seconder credentials confirmed in good standing.';
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->reset(['candidateId', 'vettingNotes']);
    }

    public function signOffCandidate(): void
    {
        $meeting = ClubCommitteeMeeting::findOrFail($this->meetingId);
        ClubAccess::authorize(auth()->user(), $meeting->club, 'manage_meetings');
        $candidateRecord = Candidate::where('club_id', $meeting->club_id)->find($this->candidateId);

        if ($candidateRecord) {
            $name = $candidateRecord->full_name;
            $email = $candidateRecord->email;
            $refId = $candidateRecord->id;
            $refType = Candidate::class;

            app(CandidateTransitionService::class)->recordCommitteeDecision($candidateRecord, auth()->user(), $this->isRecommended, $this->vettingNotes);
        } else {
            $user = $meeting->club->users()->where('users.id', $this->candidateId)->firstOrFail();
            $name = $user->name;
            $email = $user->email;
            $refId = $user->id;
            $refType = User::class;
        }

        // Record on Agenda
        ClubCommitteeAgendaItem::create([
            'committee_meeting_id' => $meeting->id,
            'order' => $meeting->agendaItems()->count() + 1,
            'item_type' => CommitteeItemType::CandidateVetting,
            'title' => "Candidate Vetting: {$name}",
            'description' => "Vetting of {$name} ({$email}) for initiation.",
            'discussion_notes' => $this->vettingNotes,
            'recommendation_text' => $this->isRecommended
                ? "RECOMMENDED: Committee approves candidate {$name} to proceed to Summons ballot."
                : 'DEFERRED: Committee requests further enquiry before proceeding.',
            'is_approved' => $this->isRecommended,
            'reference_id' => $refId,
            'reference_type' => $refType,
        ]);

        $this->closeModal();
        $this->dispatch('agendaItemAdded');
        session()->flash('success', "Candidate {$name} sign-off recorded on the committee agenda.");
    }

    public function render()
    {
        $candidate = null;
        if ($this->candidateId) {
            $meeting = ClubCommitteeMeeting::find($this->meetingId);
            $domainCand = $meeting && ClubAccess::can(auth()->user(), $meeting->club, 'manage_meetings')
                ? Candidate::where('club_id', $meeting->club_id)->find($this->candidateId)
                : null;
            if ($domainCand) {
                $candidate = (object) [
                    'id' => $domainCand->id,
                    'name' => $domainCand->full_name,
                    'email' => $domainCand->email,
                ];
            } elseif ($meeting && ClubAccess::can(auth()->user(), $meeting->club, 'manage_meetings')) {
                $candidate = $meeting->club->users()->where('users.id', $this->candidateId)->first();
            }
        }

        return view('livewire.committee.candidate-vetting-modal', [
            'candidate' => $candidate,
        ]);
    }
}
