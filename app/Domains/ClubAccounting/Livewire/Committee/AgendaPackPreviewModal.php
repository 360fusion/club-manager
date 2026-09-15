<?php

namespace App\Domains\ClubAccounting\Livewire\Committee;

use App\Domains\ClubAccounting\Enums\AttendanceType;
use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Domains\ClubAccounting\Services\Governance\CommitteePackCompilerService;
use App\Models\Club;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class AgendaPackPreviewModal extends Component
{
    public string $clubSlug;
    public int $meetingId;
    public bool $isOpen = false;

    // Dual-tab: 'pdf' or 'email'
    public string $activeTab = 'pdf';

    // Email dispatch fields
    public string $emailSubject = '';
    public string $emailBody = '';
    public array $selectedRecipientIds = [];
    public bool $includePdfAttachment = true;

    // Processing state
    public bool $isSending = false;

    public function mount(string $clubSlug, int $meetingId): void
    {
        $this->clubSlug = $clubSlug;
        $this->meetingId = $meetingId;
        $this->initModalData();
    }

    #[On('open-agenda-pack-modal')]
    public function openModal(): void
    {
        $this->initModalData();
        $this->isOpen = true;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
    }

    public function initModalData(): void
    {
        $meeting = $this->getMeeting();
        $club = $meeting->club;

        $dateFormatted = $meeting->meeting_date ? $meeting->meeting_date->format('d M Y') : Carbon::now()->format('d M Y');
        $this->emailSubject = "{$club->name} - Committee Meeting Agenda Pack - {$dateFormatted}";

        $compiler = app(CommitteePackCompilerService::class);
        $this->emailBody = $compiler->compileEmailBody($meeting);

        // Select all committee attendees marked as present or remote link by default
        $this->selectedRecipientIds = $meeting->attendees
            ->whereIn('attendance_type', [AttendanceType::Present, AttendanceType::RemoteLink])
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        // If attendees don't have user_id linked yet, fallback to club committee members
        if (empty($this->selectedRecipientIds)) {
            $this->selectedRecipientIds = $club->users()
                ->wherePivotIn('committee_role', ['chair', 'secretary', 'member'])
                ->pluck('users.id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        // If still empty, select all club users
        if (empty($this->selectedRecipientIds)) {
            $this->selectedRecipientIds = $club->users()->pluck('users.id')->map(fn ($id) => (int) $id)->values()->all();
        }
    }

    public function toggleRecipient(int $userId): void
    {
        if (in_array($userId, $this->selectedRecipientIds)) {
            $this->selectedRecipientIds = array_values(array_diff($this->selectedRecipientIds, [$userId]));
        } else {
            $this->selectedRecipientIds[] = $userId;
        }
    }

    public function selectAllRecipients(): void
    {
        $meeting = $this->getMeeting();
        $userIds = $meeting->attendees->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->all();
        if (empty($userIds)) {
            $userIds = $meeting->club->users()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        }
        $this->selectedRecipientIds = array_values(array_unique($userIds));
    }

    public function deselectAllRecipients(): void
    {
        $this->selectedRecipientIds = [];
    }

    public function sendAgendaPack(): void
    {
        $this->validate([
            'emailSubject' => 'required|string|max:255',
            'emailBody' => 'required|string',
            'selectedRecipientIds' => 'required|array|min:1',
        ], [
            'selectedRecipientIds.min' => 'Please select at least one brethren recipient to dispatch the pack to.',
        ]);

        $this->isSending = true;

        $meeting = $this->getMeeting();
        $compiler = app(CommitteePackCompilerService::class);

        $sentCount = $compiler->dispatchPack(
            meeting: $meeting,
            recipientMemberIds: $this->selectedRecipientIds,
            emailSubject: $this->emailSubject,
            customEmailBody: $this->emailBody,
            attachPdf: $this->includePdfAttachment,
        );

        $this->isSending = false;
        $this->isOpen = false;

        $message = "Agenda Pack successfully dispatched to {$sentCount} committee " . Str::plural('member', $sentCount) . '.';
        session()->flash('success', $message);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $message,
            'sent_count' => $sentCount,
        ]);

        $this->dispatch('pack-dispatched');
    }

    public function downloadPdf()
    {
        return redirect()->route('admin.committee.pack.pdf', [
            'clubSlug' => $this->clubSlug,
            'meetingId' => $this->meetingId,
            'download' => 1,
        ]);
    }

    public function getMeeting(): ClubCommitteeMeeting
    {
        $club = Club::where('slug', $this->clubSlug)->firstOrFail();
        return ClubCommitteeMeeting::where('club_id', $club->id)
            ->where('id', $this->meetingId)
            ->with([
                'club',
                'chair',
                'secretary',
                'attendees.user',
                'agendaItems' => fn ($q) => $q->orderBy('order'),
                'tasks.assignedTo',
                'noticesOfMotion'
            ])
            ->firstOrFail();
    }

    public function render()
    {
        $meeting = $this->getMeeting();

        return view('livewire.committee.agenda-pack-preview-modal', [
            'meeting' => $meeting,
            'club' => $meeting->club,
            'attendees' => $meeting->attendees,
        ]);
    }
}
