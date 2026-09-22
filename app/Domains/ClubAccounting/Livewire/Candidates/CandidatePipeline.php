<?php

namespace App\Domains\ClubAccounting\Livewire\Candidates;

use App\Domains\ClubAccounting\Enums\CandidateStage;
use App\Domains\ClubAccounting\Livewire\Candidates\Concerns\HandlesCandidateActions;
use App\Domains\ClubAccounting\Models\Candidate;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Services\CandidateTransitionService;
use App\Models\Club;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The board: every candidate in the process at a glance, with the next step, reject, close and hold on each card.
 * The full details, notes and history are on the candidate's own page.
 */
class CandidatePipeline extends Component
{
    use HandlesCandidateActions;

    #[Locked]
    public string $clubSlug;

    public string $search = '';

    public ?string $stageFilter = null;

    public string $viewMode = 'kanban'; // kanban, list

    public bool $showCandidateModal = false;

    // Candidate form fields (adding a new enquiry, or a quick edit)
    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $phone = '';

    public ?string $date_of_birth = null;

    public string $occupation = '';

    public string $address = '';

    public string $postcode = '';

    public string $source = '';

    public string $source_note = '';

    public string $notes = '';

    public function mount(string $clubSlug): void
    {
        $this->clubSlug = $clubSlug;
    }

    public function openCreateModal(): void
    {
        $this->resetFormFields();
        $this->showCandidateModal = true;
    }

    public function editCandidate(int $id): void
    {
        $candidate = $this->candidateFor($id);
        $this->candidateId = $candidate->id;
        $this->first_name = $candidate->first_name;
        $this->last_name = $candidate->last_name;
        $this->email = $candidate->email ?? '';
        $this->phone = $candidate->phone ?? '';
        $this->date_of_birth = $candidate->date_of_birth?->format('Y-m-d');
        $this->occupation = $candidate->occupation ?? '';
        $this->address = $candidate->address ?? '';
        $this->postcode = $candidate->postcode ?? '';
        $this->source = $candidate->source ?? '';
        $this->source_note = $candidate->source_note ?? '';
        $this->notes = $candidate->notes ?? '';

        $this->showCandidateModal = true;
    }

    public function saveCandidate(CandidateTransitionService $service): void
    {
        $club = $this->getClub();

        $this->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'date_of_birth' => 'nullable|date',
            'occupation' => 'nullable|string|max:150',
            'address' => 'nullable|string|max:10000',
            'postcode' => 'nullable|string|max:20',
            'source' => 'nullable|in:website,member,event,social,other',
            'source_note' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:10000',
        ]);

        $data = [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'date_of_birth' => $this->date_of_birth ?: null,
            'occupation' => $this->occupation ?: null,
            'address' => $this->address ?: null,
            'postcode' => $this->postcode ?: null,
            'source' => $this->source ?: null,
            'source_note' => $this->source_note ?: null,
            'notes' => $this->notes ?: null,
        ];

        if ($this->candidateId) {
            $candidate = $service->updateDetails($this->candidateFor($this->candidateId), $data, auth()->user());
            session()->flash('success', "Candidate '{$candidate->full_name}' updated successfully.");
        } else {
            $duplicate = $this->email !== '' && Candidate::where('club_id', $club->id)->where('email', $this->email)->exists();
            $candidate = Candidate::create($data + ['club_id' => $club->id, 'stage' => CandidateStage::Enquiry, 'stage_entered_at' => now(), 'owner_user_id' => auth()->id()]);
            $service->addNote($candidate, auth()->user(), 'note', 'Enquiry received'.($this->source ? ' ('.$this->source.')' : ''), $this->notes ?: null);
            session()->flash('success', "Candidate '{$candidate->full_name}' added to the pipeline.".($duplicate ? ' Note: someone with this email address is already on the board.' : ''));
        }

        $this->showCandidateModal = false;
        $this->resetFormFields();
    }

    protected function getClub(): Club
    {
        return Club::where('slug', $this->clubSlug)->firstOrFail();
    }

    private function resetFormFields(): void
    {
        $this->reset(['candidateId', 'first_name', 'last_name', 'email', 'phone', 'date_of_birth', 'occupation', 'address', 'postcode', 'source', 'source_note', 'notes']);
        $this->resetErrorBag();
    }

    public function render()
    {
        $club = $this->getClub();

        $query = Candidate::with(['proposer', 'seconder', 'convertedMember', 'owner'])->where('club_id', $club->id);

        if ($this->search !== '') {
            $query->search($this->search);
        }

        if ($this->stageFilter) {
            $query->stage($this->stageFilter);
        }

        $all = $query->orderBy('stage_entered_at')->orderBy('id')->get();

        $board = [];
        foreach (CandidateStage::board() as $stage) {
            $board[$stage->value] = ['stage' => $stage, 'candidates' => $all->filter(fn (Candidate $c) => $c->stage === $stage)->values()];
        }

        $completed = [
            'initiated' => $all->filter(fn (Candidate $c) => $c->stage === CandidateStage::Initiated)->sortByDesc('initiation_date')->values(),
            'rejected' => $all->filter(fn (Candidate $c) => $c->stage === CandidateStage::Rejected)->sortByDesc('outcome_at')->values(),
            'closed' => $all->filter(fn (Candidate $c) => $c->stage === CandidateStage::Withdrawn)->sortByDesc('outcome_at')->values(),
        ];

        return view('livewire.candidates.candidate-pipeline', [
            'club' => $club,
            'stages' => CandidateStage::cases(),
            'board' => $board,
            'completed' => $completed,
            'allCandidates' => $all,
            'activeCount' => $all->filter(fn (Candidate $c) => $c->isActive())->count(),
            'activeMembers' => Member::where('club_id', $club->id)->active()->orderBy('last_name')->get(),
            'reasons' => $this->outcomeReasons(),
        ])->layout('components.layouts.app', [
            'title' => 'Candidate Pipeline — '.$club->name,
            'club' => $club,
        ]);
    }
}
