<?php

namespace App\Domains\ClubAccounting\Livewire\Committee;

use App\Domains\ClubAccounting\Enums\CommitteeMeetingStatus;
use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Models\Club;
use App\Models\Meeting;
use Carbon\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class CreateCommitteeMeetingModal extends Component
{
    #[Locked]
    public ?string $clubSlug = null;

    public bool $isOpen = false;

    public ?string $meeting_date = null;

    public string $title = '';

    public string $location = '';

    public string $time_opened = '19:00';

    public ?int $linked_regular_meeting_id = null;

    public bool $isCustomTitle = false;

    public function mount(?string $clubSlug = null): void
    {
        $this->clubSlug = $clubSlug;
        $this->initDefaults();
    }

    #[On('open-create-committee-meeting-modal')]
    public function openModal(): void
    {
        $this->initDefaults();
        $this->isOpen = true;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
    }

    public function initDefaults(): void
    {
        $club = $this->getClub();

        // 2. Default location to lodge meeting venue, address, or fallback
        $this->location = $club->meeting_venue
            ?? $club->address
            ?? $club->settings['default_meeting_location']
            ?? $club->settings['meeting_venue']
            ?? $club->settings['meeting_location']
            ?? Meeting::where('club_id', $club->id)->whereNotNull('venue')->where('venue', '!=', '')->latest('meeting_date')->value('venue')
            ?? 'Masonic Hall, Wellington Street, Stockton-on-Tees';

        if (empty(trim($this->location))) {
            $this->location = 'Masonic Hall';
        }

        // Default date to today or upcoming scheduled date
        if (empty($this->meeting_date)) {
            $this->meeting_date = now()->toDateString();
        }

        $this->time_opened = '19:00';
        $this->linked_regular_meeting_id = null;
        $this->isCustomTitle = false;
        $this->generateTitle();
    }

    /**
     * Re-calculate title whenever meeting_date changes.
     */
    public function updatedMeetingDate(): void
    {
        if (! $this->isCustomTitle || empty(trim($this->title))) {
            $this->generateTitle();
        }
    }

    public function updatedTitle(): void
    {
        // If user explicitly typed or edited title, mark it as custom unless blank
        $this->isCustomTitle = ! empty(trim($this->title));
        if (empty(trim($this->title))) {
            $this->generateTitle();
        }
    }

    /**
     * Relative Pre-Meeting Date Suggestion (UGLE Committee offset).
     * When linked regular lodge meeting is selected, default to 9 days prior.
     */
    public function updatedLinkedRegularMeetingId(): void
    {
        if ($this->linked_regular_meeting_id) {
            $club = $this->getClub();
            $regularMeeting = Meeting::where('club_id', $club->id)->find($this->linked_regular_meeting_id);

            if ($regularMeeting && $regularMeeting->meeting_date) {
                $offsetDays = (int) ($club->settings['committee_meeting_offset_days'] ?? 9);
                $suggestedDate = Carbon::parse($regularMeeting->meeting_date)->subDays($offsetDays);
                $this->meeting_date = $suggestedDate->toDateString();
                $this->isCustomTitle = false;
                $this->generateTitle();

                if (! empty($regularMeeting->venue) && empty(trim($this->location))) {
                    $this->location = $regularMeeting->venue;
                }
            }
        }
    }

    public function generateTitle(): void
    {
        if ($this->meeting_date) {
            try {
                $formattedDate = Carbon::parse($this->meeting_date)->format('jS F Y');
                $this->title = "Committee Meeting – {$formattedDate}";
            } catch (\Throwable $e) {
                // Ignore parse errors during typing
            }
        }
    }

    public function save()
    {
        // Guarantee title is set if cleared or empty
        if (empty(trim($this->title))) {
            $this->generateTitle();
        }

        if (empty(trim($this->title))) {
            $this->title = 'Committee Meeting – '.now()->format('jS F Y');
        }

        $validated = $this->validate([
            'meeting_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'time_opened' => ['nullable', 'string', 'max:20'],
            'linked_regular_meeting_id' => ['nullable', 'integer'],
        ]);

        $club = $this->getClub();

        $meetingDateTime = Carbon::parse($validated['meeting_date']);
        if (! empty($validated['time_opened']) && str_contains($validated['time_opened'], ':')) {
            $parts = explode(':', $validated['time_opened']);
            $meetingDateTime->setTime((int) $parts[0], (int) $parts[1]);
        }

        $meeting = ClubCommitteeMeeting::create([
            'club_id' => $club->id,
            'title' => $validated['title'],
            'meeting_date' => $meetingDateTime,
            'time_opened' => $validated['time_opened'] ?? '19:00',
            'location' => $validated['location'],
            'linked_regular_meeting_id' => $validated['linked_regular_meeting_id'] ?? null,
            'status' => CommitteeMeetingStatus::Scheduled,
        ]);

        $this->dispatch('meetingCreated', meetingId: $meeting->id);
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Committee meeting '{$meeting->title}' scheduled successfully.",
        ]);

        $this->isOpen = false;

        return redirect()->route('admin.committee.workspace', [
            'clubSlug' => $club->slug,
            'meetingId' => $meeting->id,
        ]);
    }

    public function getClub(): Club
    {
        if (! empty($this->clubSlug)) {
            return Club::where('slug', $this->clubSlug)->firstOrFail();
        }

        if (function_exists('current_club') && current_club()) {
            return current_club();
        }

        if (auth()->check() && auth()->user()->clubs->isNotEmpty()) {
            return auth()->user()->clubs->first();
        }

        return Club::firstOrFail();
    }

    public function render()
    {
        $club = $this->getClub();

        $upcomingRegularMeetings = Meeting::where('club_id', $club->id)
            ->where('meeting_date', '>=', now()->startOfDay())
            ->orderBy('meeting_date')
            ->take(10)
            ->get();

        return view('livewire.committee.create-committee-meeting-modal', [
            'club' => $club,
            'upcomingRegularMeetings' => $upcomingRegularMeetings,
        ]);
    }
}
