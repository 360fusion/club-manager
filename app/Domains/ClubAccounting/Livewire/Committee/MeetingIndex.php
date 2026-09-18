<?php

namespace App\Domains\ClubAccounting\Livewire\Committee;

use App\Domains\ClubAccounting\Enums\CommitteeMeetingStatus;
use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Models\Club;
use App\Models\Meeting;
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
    public ?int $linked_regular_meeting_id = null;
    public bool $isCustomTitle = false;

    // Edit Modal State
    public bool $showEditModal = false;
    public ?int $editingMeetingId = null;
    public string $editTitle = '';
    public string $editDate = '';
    public string $editLocation = '';
    public string $editStatus = 'draft';

    public function mount(string $clubSlug): void
    {
        $this->clubSlug = $clubSlug;
        $this->newDate = Carbon::now()->addDays(7)->format('Y-m-d\TH:i');
        $this->generateTitleFromDate();
    }

    public function openCreateModal(): void
    {
        $club = Club::where('slug', $this->clubSlug)->firstOrFail();

        if (empty($this->newDate)) {
            $this->newDate = Carbon::now()->addDays(7)->format('Y-m-d\TH:i');
        }

        if (empty($this->newLocation)) {
            $this->newLocation = $club->meeting_venue 
                ?? $club->address 
                ?? $club->settings['default_meeting_location']
                ?? $club->settings['meeting_venue']
                ?? $club->settings['meeting_location']
                ?? Meeting::where('club_id', $club->id)->whereNotNull('venue')->where('venue', '!=', '')->latest('meeting_date')->value('venue')
                ?? 'Masonic Hall, Wellington Street, Stockton-on-Tees';
        }

        if (empty(trim($this->newLocation))) {
            $this->newLocation = 'Masonic Hall';
        }

        if (empty($this->newTitle)) {
            $this->generateTitleFromDate();
        }

        $this->isCustomTitle = false;
        $this->showCreateModal = true;
    }

    public function updatedNewTitle(): void
    {
        $this->isCustomTitle = !empty(trim($this->newTitle));
        if (empty(trim($this->newTitle))) {
            $this->generateTitleFromDate();
        }
    }

    public function updatedNewDate(): void
    {
        if (!$this->isCustomTitle || empty(trim($this->newTitle))) {
            $this->generateTitleFromDate();
        }
    }

    public function updatedLinkedRegularMeetingId(): void
    {
        if ($this->linked_regular_meeting_id) {
            $club = Club::where('slug', $this->clubSlug)->firstOrFail();
            $regularMeeting = Meeting::where('club_id', $club->id)->find($this->linked_regular_meeting_id);

            if ($regularMeeting && $regularMeeting->meeting_date) {
                $offsetDays = (int) ($club->settings['committee_meeting_offset_days'] ?? 9);
                $suggestedDate = Carbon::parse($regularMeeting->meeting_date)->subDays($offsetDays);
                $this->newDate = $suggestedDate->format('Y-m-d\T19:00');
                $this->generateTitleFromDate();

                if (!empty($regularMeeting->venue)) {
                    $this->newLocation = $regularMeeting->venue;
                }
            }
        }
    }

    public function generateTitleFromDate(): void
    {
        if (!empty($this->newDate)) {
            try {
                $formattedDate = Carbon::parse($this->newDate)->format('jS F Y');
                $this->newTitle = "Committee Meeting – {$formattedDate}";
            } catch (\Throwable $e) {
                // Ignore parse errors during typing
            }
        }
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->reset(['newTitle', 'newLocation', 'linked_regular_meeting_id']);
    }

    public function createMeeting(): void
    {
        if (empty(trim($this->newTitle))) {
            $this->generateTitleFromDate();
        }

        if (empty(trim($this->newTitle))) {
            $this->newTitle = 'Committee Meeting – ' . now()->format('jS F Y');
        }

        $this->validate([
            'newTitle' => 'required|string|max:255',
            'newDate' => 'required|date',
            'newLocation' => 'nullable|string|max:255',
            'linked_regular_meeting_id' => 'nullable|integer',
        ]);

        $club = Club::where('slug', $this->clubSlug)->firstOrFail();

        $meeting = ClubCommitteeMeeting::create([
            'club_id' => $club->id,
            'linked_regular_meeting_id' => $this->linked_regular_meeting_id,
            'title' => $this->newTitle,
            'meeting_date' => Carbon::parse($this->newDate),
            'location' => $this->newLocation ?: 'Masonic Hall',
            'status' => CommitteeMeetingStatus::Draft,
        ]);

        $this->closeCreateModal();
        session()->flash('success', "Committee meeting '{$meeting->title}' scheduled successfully.");
    }

    public function openEditModal(int $id): void
    {
        $club = Club::where('slug', $this->clubSlug)->firstOrFail();
        $meeting = ClubCommitteeMeeting::where('club_id', $club->id)->findOrFail($id);

        $this->editingMeetingId = $meeting->id;
        $this->editTitle = $meeting->title;
        $this->editDate = $meeting->meeting_date ? $meeting->meeting_date->format('Y-m-d\TH:i') : '';
        $this->editLocation = $meeting->location ?? '';
        $this->editStatus = $meeting->status === CommitteeMeetingStatus::Scheduled ? 'scheduled' : 'draft';
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editingMeetingId = null;
        $this->resetValidation();
    }

    public function updateMeeting(): void
    {
        $this->validate([
            'editTitle' => 'required|string|max:255',
            'editDate' => 'required|date',
            'editLocation' => 'nullable|string|max:255',
            'editStatus' => 'nullable|string|in:draft,scheduled',
        ]);

        $club = Club::where('slug', $this->clubSlug)->firstOrFail();
        $meeting = ClubCommitteeMeeting::where('club_id', $club->id)->findOrFail($this->editingMeetingId);

        $statusEnum = $this->editStatus === 'scheduled'
            ? CommitteeMeetingStatus::Scheduled
            : CommitteeMeetingStatus::Draft;

        $meeting->update([
            'title' => trim($this->editTitle),
            'meeting_date' => Carbon::parse($this->editDate),
            'location' => trim($this->editLocation) ?: 'Masonic Hall',
            'status' => $statusEnum,
        ]);

        $this->closeEditModal();
        session()->flash('success', "Committee meeting '{$meeting->title}' updated successfully.");
    }

    public function render()
    {
        $club = Club::where('slug', $this->clubSlug)->firstOrFail();
        $now = Carbon::now();

        $meetings = ClubCommitteeMeeting::where('club_id', $club->id)
            ->when($this->statusFilter === 'scheduled', function ($q) use ($now) {
                $q->where('status', CommitteeMeetingStatus::Scheduled)
                  ->where('meeting_date', '>=', $now);
            })
            ->when($this->statusFilter === 'draft', function ($q) use ($now) {
                $q->whereIn('status', [CommitteeMeetingStatus::Draft, CommitteeMeetingStatus::DraftSaved])
                  ->where('meeting_date', '>=', $now);
            })
            ->when($this->statusFilter === 'past', function ($q) use ($now) {
                $q->where('meeting_date', '<', $now);
            })
            ->when(! in_array($this->statusFilter, ['all', 'scheduled', 'draft', 'past']), function ($q) {
                $q->where('status', $this->statusFilter);
            })
            ->when(!empty($this->search), fn ($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->orderByDesc('meeting_date')
            ->with(['chair', 'secretary', 'attendees', 'agendaItems', 'tasks'])
            ->get();

        $upcomingRegularMeetings = Meeting::where('club_id', $club->id)
            ->where('meeting_date', '>=', now()->startOfDay())
            ->orderBy('meeting_date')
            ->take(10)
            ->get();

        return view('livewire.committee.meeting-index', [
            'club' => $club,
            'meetings' => $meetings,
            'upcomingRegularMeetings' => $upcomingRegularMeetings,
        ])->layout('components.layouts.app', [
            'title' => 'Committee & Board Governance',
            'club' => $club,
        ]);
    }
}
