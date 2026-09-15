<?php

namespace App\Domains\ClubAccounting\Livewire\Committee;

use App\Domains\ClubAccounting\Enums\AttendanceType;
use App\Domains\ClubAccounting\Enums\CommitteeItemType;
use App\Domains\ClubAccounting\Enums\CommitteeMeetingStatus;
use App\Domains\ClubAccounting\Models\ClubCommitteeAgendaItem;
use App\Domains\ClubAccounting\Models\ClubCommitteeAttendee;
use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Domains\ClubAccounting\Services\Governance\CommitteePackCompilerService;
use App\Models\Club;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Livewire\Component;

class MeetingWorkspace extends Component
{
    public string $clubSlug;
    public int $meetingId;

    // Add Agenda Item Form
    public bool $showAgendaModal = false;
    public string $agendaTitle = '';
    public string $agendaItemType = 'general';
    public string $agendaDescription = '';

    // Add Attendee Form
    public bool $showAttendeeModal = false;
    public ?int $selectedUserId = null;
    public string $attendeeRole = 'Committee Member';
    public array $selectedMemberIds = [];
    public array $customRoles = [];
    public string $attendeeSearch = '';

    public function mount(string $clubSlug, int $meetingId): void
    {
        $this->clubSlug = $clubSlug;
        $this->meetingId = $meetingId;
    }

    public function setStatus(string $status): void
    {
        $meeting = $this->getMeeting();
        $newStatus = CommitteeMeetingStatus::from($status);

        $meeting->update([
            'status' => $newStatus,
            'finalized_at' => $newStatus === CommitteeMeetingStatus::Finalized ? Carbon::now() : null,
        ]);

        session()->flash('success', "Meeting status updated to {$newStatus->label()}.");
    }

    public function updateAttendance(int $attendeeId, string $type): void
    {
        $attendee = ClubCommitteeAttendee::where('committee_meeting_id', $this->meetingId)
            ->findOrFail($attendeeId);

        $attendee->update([
            'attendance_type' => AttendanceType::from($type),
        ]);
    }

    public function addAgendaItem(): void
    {
        $this->validate([
            'agendaTitle' => 'required|string|max:255',
            'agendaItemType' => 'required|string',
            'agendaDescription' => 'nullable|string',
        ]);

        $meeting = $this->getMeeting();
        $order = $meeting->agendaItems()->count() + 1;

        ClubCommitteeAgendaItem::create([
            'committee_meeting_id' => $meeting->id,
            'order' => $order,
            'item_type' => CommitteeItemType::from($this->agendaItemType),
            'title' => $this->agendaTitle,
            'description' => $this->agendaDescription,
        ]);

        $this->showAgendaModal = false;
        $this->reset(['agendaTitle', 'agendaDescription', 'agendaItemType']);
        session()->flash('success', 'Agenda item added.');
    }

    public function toggleAgendaApproval(int $itemId): void
    {
        $item = ClubCommitteeAgendaItem::where('committee_meeting_id', $this->meetingId)
            ->findOrFail($itemId);

        $item->update(['is_approved' => !$item->is_approved]);
    }

    public function openAttendeeModal(): void
    {
        $this->selectedMemberIds = [];
        $this->attendeeSearch = '';
        $this->showAttendeeModal = true;
    }

    public function selectAllCommittee(): void
    {
        $meeting = $this->getMeeting();
        $existingAttendeeUserIds = $meeting->attendees->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->all();

        $committeeUserIds = $meeting->club->users()
            ->withPivot('committee_role')
            ->get()
            ->filter(fn ($u) => in_array($u->pivot->committee_role ?? null, ['chair', 'secretary', 'member']))
            ->pluck('id')
            ->diff($existingAttendeeUserIds)
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $this->selectedMemberIds = array_values(array_unique(array_merge($this->selectedMemberIds, $committeeUserIds)));
    }

    public function deselectAll(): void
    {
        $this->selectedMemberIds = [];
    }

    public function addSelectedAttendees(): void
    {
        if (empty($this->selectedMemberIds)) {
            session()->flash('error', 'Please select at least one member to add to roll-call.');
            return;
        }

        $meeting = $this->getMeeting();
        $users = $meeting->club->users()
            ->whereIn('users.id', $this->selectedMemberIds)
            ->withPivot('committee_role')
            ->get();

        $addedCount = 0;
        foreach ($users as $user) {
            $commRole = $user->pivot->committee_role ?? null;
            $defaultRole = match ($commRole) {
                'chair' => 'Committee Chair',
                'secretary' => 'Committee Secretary',
                'member' => 'Committee Member',
                default => 'Non-Committee Member',
            };

            $roleTitle = !empty($this->customRoles[$user->id])
                ? trim($this->customRoles[$user->id])
                : $defaultRole;

            $attendee = ClubCommitteeAttendee::firstOrCreate([
                'committee_meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ], [
                'name' => $user->name,
                'role_title' => $roleTitle,
                'attendance_type' => AttendanceType::Present,
            ]);

            if ($attendee->wasRecentlyCreated) {
                $addedCount++;
            }
        }

        $this->showAttendeeModal = false;
        $this->reset(['selectedMemberIds', 'customRoles', 'selectedUserId', 'attendeeRole', 'attendeeSearch']);
        session()->flash('success', "Added {$addedCount} " . Str::plural('member', $addedCount) . " to committee roll-call.");
    }

    public function addAttendee(): void
    {
        $meeting = $this->getMeeting();
        $user = User::findOrFail($this->selectedUserId);

        ClubCommitteeAttendee::firstOrCreate([
            'committee_meeting_id' => $meeting->id,
            'user_id' => $user->id,
        ], [
            'name' => $user->name,
            'role_title' => $this->attendeeRole,
            'attendance_type' => AttendanceType::Present,
        ]);

        $this->showAttendeeModal = false;
        $this->reset(['selectedUserId', 'attendeeRole']);
        session()->flash('success', "Added {$user->name} to committee roll-call.");
    }

    public function removeAttendee(int $attendeeId): void
    {
        ClubCommitteeAttendee::where('committee_meeting_id', $this->meetingId)
            ->where('id', $attendeeId)
            ->delete();

        session()->flash('success', 'Attendee removed from roll-call.');
    }

    private function getMeeting(): ClubCommitteeMeeting
    {
        $club = Club::where('slug', $this->clubSlug)->firstOrFail();
        return ClubCommitteeMeeting::where('club_id', $club->id)
            ->where('id', $this->meetingId)
            ->firstOrFail();
    }

    public function render(CommitteePackCompilerService $compiler)
    {
        $meeting = $this->getMeeting();
        $packData = $compiler->compilePackData($meeting);

        $existingAttendeeUserIds = $meeting->attendees->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->all();

        $allMembers = $meeting->club->users()
            ->withPivot('role', 'rank', 'committee_role')
            ->get()
            ->sortBy(function ($user) {
                $priority = match ($user->pivot->committee_role ?? null) {
                    'chair' => 1,
                    'secretary' => 2,
                    'member' => 3,
                    default => 4,
                };
                return $priority . '_' . strtolower($user->name);
            })
            ->values();

        $filteredMembers = $allMembers;
        if (!empty($this->attendeeSearch)) {
            $search = strtolower(trim($this->attendeeSearch));
            $filteredMembers = $filteredMembers->filter(function ($user) use ($search) {
                return str_contains(strtolower($user->name), $search)
                    || str_contains(strtolower($user->email), $search)
                    || str_contains(strtolower($user->pivot->committee_role ?? ''), $search);
            })->values();
        }

        $committeeMembers = $filteredMembers->filter(fn ($u) => in_array($u->pivot->committee_role ?? '', ['chair', 'secretary', 'member']))->values();
        $nonCommitteeMembers = $filteredMembers->filter(fn ($u) => !in_array($u->pivot->committee_role ?? '', ['chair', 'secretary', 'member']))->values();

        return view('livewire.committee.meeting-workspace', array_merge($packData, [
            'clubMembers' => $allMembers,
            'committeeMembers' => $committeeMembers,
            'nonCommitteeMembers' => $nonCommitteeMembers,
            'existingAttendeeUserIds' => $existingAttendeeUserIds,
        ]))->layout('components.layouts.app');
    }
}
