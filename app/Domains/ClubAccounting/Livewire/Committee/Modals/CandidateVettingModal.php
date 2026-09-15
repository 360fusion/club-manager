<?php

namespace App\Domains\ClubAccounting\Livewire\Committee\Modals;

use App\Domains\ClubAccounting\Enums\CommitteeItemType;
use App\Domains\ClubAccounting\Models\ClubCommitteeAgendaItem;
use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Models\User;
use Livewire\Component;

class CandidateVettingModal extends Component
{
    public int $meetingId;
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
        $this->vettingNotes = "Candidate vetted under UGLE Rule 159. Identity verified, age qualification and proposer/seconder credentials confirmed in good standing.";
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->reset(['candidateId', 'vettingNotes']);
    }

    public function signOffCandidate(): void
    {
        $candidate = User::findOrFail($this->candidateId);
        $meeting = ClubCommitteeMeeting::findOrFail($this->meetingId);

        // Record on Agenda
        ClubCommitteeAgendaItem::create([
            'committee_meeting_id' => $meeting->id,
            'order' => $meeting->agendaItems()->count() + 1,
            'item_type' => CommitteeItemType::CandidateVetting,
            'title' => "Candidate Vetting: {$candidate->name}",
            'description' => "Vetting of {$candidate->name} ({$candidate->email}) for initiation.",
            'discussion_notes' => $this->vettingNotes,
            'recommendation_text' => $this->isRecommended
                ? "RECOMMENDED: Committee approves candidate {$candidate->name} to proceed to Summons ballot."
                : "DEFERRED: Committee requests further enquiry before proceeding.",
            'is_approved' => $this->isRecommended,
            'reference_id' => $candidate->id,
            'reference_type' => User::class,
        ]);

        $this->closeModal();
        $this->dispatch('agendaItemAdded');
        session()->flash('success', "Candidate {$candidate->name} sign-off recorded on the committee agenda.");
    }

    public function render()
    {
        $candidate = $this->candidateId ? User::find($this->candidateId) : null;

        return view('livewire.committee.candidate-vetting-modal', [
            'candidate' => $candidate,
        ]);
    }
}
