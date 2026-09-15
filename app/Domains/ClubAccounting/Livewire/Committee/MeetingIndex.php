<?php

namespace App\Domains\ClubAccounting\Livewire\Committee;

use App\Domains\ClubAccounting\Enums\CommitteeMeetingStatus;
use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Models\Club;
use Carbon\Carbon;
use Livewire\Component;

class MeetingIndex extends Component
{
    public string $clubSlug;
    public string $statusFilter = 'all';
    public string $search = '';

    // Create Modal State
    public bool $showCreateModal = false;
    public string $newTitle = '';
    public string $newDate = '';
    public string $newLocation = '';

    public function mount(string $clubSlug): void
    {
        $this->clubSlug = $clubSlug;
        $this->newDate = Carbon::now()->addDays(7)->format('Y-m-d\TH:i');
    }

    public function openCreateModal(): void
    {
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->reset(['newTitle', 'newLocation']);
    }

    public function createMeeting(): void
    {
        $this->validate([
            'newTitle' => 'required|string|max:255',
            'newDate' => 'required|date',
            'newLocation' => 'nullable|string|max:255',
        ]);

        $club = Club::where('slug', $this->clubSlug)->firstOrFail();

        $meeting = ClubCommitteeMeeting::create([
            'club_id' => $club->id,
            'title' => $this->newTitle,
            'meeting_date' => Carbon::parse($this->newDate),
            'location' => $this->newLocation ?: 'Lodge Committee Room',
            'status' => CommitteeMeetingStatus::Scheduled,
        ]);

        $this->closeCreateModal();
        session()->flash('success', "Committee meeting '{$meeting->title}' scheduled successfully.");
    }

    public function render()
    {
        $club = Club::where('slug', $this->clubSlug)->firstOrFail();

        $meetings = ClubCommitteeMeeting::where('club_id', $club->id)
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when(!empty($this->search), fn ($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->orderByDesc('meeting_date')
            ->with(['chair', 'secretary', 'attendees', 'agendaItems', 'tasks'])
            ->get();

        return view('livewire.committee.meeting-index', [
            'club' => $club,
            'meetings' => $meetings,
        ])->layout('components.layouts.app', [
            'title' => 'Committee & Board Governance',
            'club' => $club,
        ]);
    }
}
