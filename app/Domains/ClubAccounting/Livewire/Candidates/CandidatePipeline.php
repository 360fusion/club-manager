<?php

namespace App\Domains\ClubAccounting\Livewire\Candidates;

use App\Domains\ClubAccounting\Enums\CandidateStage;
use App\Domains\ClubAccounting\Models\Candidate;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Services\CandidateTransitionService;
use App\Models\Club;
use Carbon\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class CandidatePipeline extends Component
{
    use WithPagination;

    #[Locked]
    public string $clubSlug;

    public string $search = '';

    public ?string $stageFilter = null;

    public string $viewMode = 'kanban'; // kanban, list

    // Modals visibility
    public bool $showCandidateModal = false;

    public bool $showFormPModal = false;

    public bool $showInitiationModal = false;

    // Selected Candidate ID
    #[Locked]
    public ?int $candidateId = null;

    // Candidate Form Fields
    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $phone = '';

    public ?string $date_of_birth = null;

    public string $occupation = '';

    public string $address = '';

    public string $postcode = '';

    public string $stage = 'enquiry';

    public string $notes = '';

    // Form P Vetting Fields
    public ?int $proposer_member_id = null;

    public ?int $seconder_member_id = null;

    public ?string $form_p_signed_at = null;

    public bool $belief_in_supreme_being = false;

    public bool $no_criminal_record = false;

    public bool $no_bankruptcies = false;

    public bool $rule_159_cleared = false;

    public ?string $hermes_clearance_date = null;

    // Initiation Fields
    public ?string $initiation_date = null;

    public function mount(string $clubSlug): void
    {
        $this->clubSlug = $clubSlug;
        $this->initiation_date = Carbon::now()->format('Y-m-d');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStageFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetFormFields();
        $this->showCandidateModal = true;
    }

    public function editCandidate(int $id): void
    {
        $candidate = Candidate::where('club_id', $this->getClub()->id)->findOrFail($id);
        $this->candidateId = $candidate->id;
        $this->first_name = $candidate->first_name;
        $this->last_name = $candidate->last_name;
        $this->email = $candidate->email ?? '';
        $this->phone = $candidate->phone ?? '';
        $this->date_of_birth = $candidate->date_of_birth ? $candidate->date_of_birth->format('Y-m-d') : null;
        $this->occupation = $candidate->occupation ?? '';
        $this->address = $candidate->address ?? '';
        $this->postcode = $candidate->postcode ?? '';
        $this->stage = $candidate->stage->value;
        $this->notes = $candidate->notes ?? '';

        $this->showCandidateModal = true;
    }

    public function saveCandidate(): void
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
            'stage' => 'required|string|max:50',
            'notes' => 'nullable|string|max:10000',
        ]);

        $data = [
            'club_id' => $club->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth ?: null,
            'occupation' => $this->occupation,
            'address' => $this->address,
            'postcode' => $this->postcode,
            'stage' => $this->stage,
            'notes' => $this->notes,
        ];

        if ($this->candidateId) {
            $candidate = Candidate::where('club_id', $club->id)->findOrFail($this->candidateId);
            $candidate->update($data);
            session()->flash('success', "Candidate '{$candidate->full_name}' updated successfully.");
        } else {
            $candidate = Candidate::create($data);
            session()->flash('success', "Candidate '{$candidate->full_name}' added to pipeline.");
        }

        $this->showCandidateModal = false;
        $this->resetFormFields();
    }

    public function moveStage(int $id, string $targetStageStr, CandidateTransitionService $transitionService): void
    {
        $club = $this->getClub();
        $candidate = Candidate::where('club_id', $club->id)->findOrFail($id);

        try {
            $targetStage = CandidateStage::from($targetStageStr);

            if ($targetStage === CandidateStage::Initiated) {
                $this->openInitiationModal($candidate->id);

                return;
            }

            $transitionService->transitionStage($candidate, $targetStage);
            session()->flash('success', "Candidate '{$candidate->full_name}' moved to stage: ".$targetStage->label());
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            // Open Form P modal if proposer/seconder is missing
            $this->openFormPModal($candidate->id);
        }
    }

    public function openFormPModal(int $id): void
    {
        $club = $this->getClub();
        $candidate = Candidate::where('club_id', $club->id)->findOrFail($id);

        $this->candidateId = $candidate->id;
        $this->proposer_member_id = $candidate->proposer_member_id;
        $this->seconder_member_id = $candidate->seconder_member_id;
        $this->form_p_signed_at = $candidate->form_p_signed_at ? $candidate->form_p_signed_at->format('Y-m-d') : Carbon::now()->format('Y-m-d');
        $this->belief_in_supreme_being = (bool) $candidate->belief_in_supreme_being;
        $this->no_criminal_record = (bool) $candidate->no_criminal_record;
        $this->no_bankruptcies = (bool) $candidate->no_bankruptcies;
        $this->rule_159_cleared = (bool) $candidate->rule_159_cleared;
        $this->hermes_clearance_date = $candidate->hermes_clearance_date ? $candidate->hermes_clearance_date->format('Y-m-d') : null;

        $this->showFormPModal = true;
    }

    public function saveFormPVetting(CandidateTransitionService $transitionService): void
    {
        $club = $this->getClub();
        $candidate = Candidate::where('club_id', $club->id)->findOrFail($this->candidateId);

        $this->validate([
            'proposer_member_id' => 'required|exists:club_acc_members,id',
            'seconder_member_id' => 'required|exists:club_acc_members,id|different:proposer_member_id',
            'form_p_signed_at' => 'required|date',
            'belief_in_supreme_being' => 'accepted',
            'no_criminal_record' => 'accepted',
            'no_bankruptcies' => 'accepted',
            'rule_159_cleared' => 'boolean',
            'hermes_clearance_date' => 'nullable|date',
        ], [
            'belief_in_supreme_being.accepted' => 'Statutory declaration of belief in a Supreme Being is required.',
            'no_criminal_record.accepted' => 'Statutory declaration of no unspent criminal convictions is required.',
            'no_bankruptcies.accepted' => 'Statutory declaration of no un-discharged bankruptcies is required.',
            'seconder_member_id.different' => 'Seconder must be a different member than the Proposer.',
        ]);

        $transitionService->updateFormPVetting($candidate, [
            'proposer_member_id' => $this->proposer_member_id,
            'seconder_member_id' => $this->seconder_member_id,
            'form_p_signed_at' => $this->form_p_signed_at,
            'belief_in_supreme_being' => $this->belief_in_supreme_being,
            'no_criminal_record' => $this->no_criminal_record,
            'no_bankruptcies' => $this->no_bankruptcies,
            'rule_159_cleared' => $this->rule_159_cleared,
            'hermes_clearance_date' => $this->hermes_clearance_date ?: null,
        ]);

        session()->flash('success', "Form P statutory vetting saved for candidate '{$candidate->full_name}'.");
        $this->showFormPModal = false;
    }

    public function openInitiationModal(int $id): void
    {
        $club = $this->getClub();
        $candidate = Candidate::where('club_id', $club->id)->findOrFail($id);

        $this->candidateId = $candidate->id;
        $this->initiation_date = $candidate->initiation_date ? $candidate->initiation_date->format('Y-m-d') : Carbon::now()->format('Y-m-d');
        $this->showInitiationModal = true;
    }

    public function confirmInitiation(CandidateTransitionService $transitionService): void
    {
        $club = $this->getClub();
        $candidate = Candidate::where('club_id', $club->id)->findOrFail($this->candidateId);

        $this->validate([
            'initiation_date' => 'required|date',
        ]);

        $member = $transitionService->convertCandidateToMember($candidate, Carbon::parse($this->initiation_date));

        session()->flash('success', "Candidate '{$candidate->full_name}' initiated successfully! Member record instantiated with rank Bro.");
        $this->showInitiationModal = false;
    }

    public function deleteCandidate(int $id): void
    {
        $club = $this->getClub();
        $candidate = Candidate::where('club_id', $club->id)->findOrFail($id);
        $name = $candidate->full_name;
        $candidate->delete();

        session()->flash('success', "Candidate '{$name}' removed from pipeline.");
    }

    private function getClub(): Club
    {
        return Club::where('slug', $this->clubSlug)->firstOrFail();
    }

    private function resetFormFields(): void
    {
        $this->reset([
            'candidateId',
            'first_name',
            'last_name',
            'email',
            'phone',
            'date_of_birth',
            'occupation',
            'address',
            'postcode',
            'stage',
            'notes',
            'proposer_member_id',
            'seconder_member_id',
            'form_p_signed_at',
            'belief_in_supreme_being',
            'no_criminal_record',
            'no_bankruptcies',
            'rule_159_cleared',
            'hermes_clearance_date',
        ]);
        $this->stage = 'enquiry';
        $this->initiation_date = Carbon::now()->format('Y-m-d');
    }

    public function render()
    {
        $club = $this->getClub();

        $query = Candidate::with(['proposer', 'seconder', 'convertedMember'])
            ->where('club_id', $club->id);

        if (! empty($this->search)) {
            $query->search($this->search);
        }

        if (! empty($this->stageFilter)) {
            $query->stage($this->stageFilter);
        }

        $allCandidates = $query->orderBy('created_at', 'desc')->get();

        // Group by stages for Kanban view
        $stages = CandidateStage::cases();
        $kanbanColumns = [];
        foreach ($stages as $stg) {
            $kanbanColumns[$stg->value] = [
                'stage' => $stg,
                'candidates' => $allCandidates->filter(fn ($c) => $c->stage === $stg)->values(),
            ];
        }

        // Active members for proposer / seconder dropdowns
        $activeMembers = Member::where('club_id', $club->id)
            ->active()
            ->orderBy('last_name')
            ->get();

        return view('livewire.candidates.candidate-pipeline', [
            'club' => $club,
            'stages' => $stages,
            'kanbanColumns' => $kanbanColumns,
            'allCandidates' => $allCandidates,
            'activeMembers' => $activeMembers,
        ])->layout('components.layouts.app', [
            'title' => 'Candidate Pipeline — '.$club->name,
            'club' => $club,
        ]);
    }
}
