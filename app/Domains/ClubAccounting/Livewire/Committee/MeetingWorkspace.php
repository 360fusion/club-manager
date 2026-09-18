<?php

namespace App\Domains\ClubAccounting\Livewire\Committee;

use App\Domains\ClubAccounting\Enums\AttendanceType;
use App\Domains\ClubAccounting\Enums\CommitteeItemType;
use App\Domains\ClubAccounting\Enums\CommitteeMeetingStatus;
use App\Domains\ClubAccounting\Models\AnnualOfficerRoster;
use App\Domains\ClubAccounting\Models\ClubCommitteeAgendaItem;
use App\Domains\ClubAccounting\Models\ClubCommitteeAttendee;
use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Services\Governance\CommitteePackCompilerService;
use App\Models\Club;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class MeetingWorkspace extends Component
{
    public string $clubSlug;
    public int $meetingId;

    #[On('pack-dispatched')]
    public function onPackDispatched(): void
    {
        $meeting = $this->getMeeting();
        $meeting->update(['status' => CommitteeMeetingStatus::Scheduled]);
    }

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

    // Edit Meeting Details Form
    public bool $showEditModal = false;
    public string $editTitle = '';
    public string $editDate = '';
    public string $editLocation = '';
    public string $editStatus = 'draft';

    // Charity Grant Proposal Form inside Meeting Workspace
    public bool $showCharityModal = false;
    public string $grantRecipient = '';
    public string $grantPurpose = '';
    public string $grantAmount = '0.00';
    public ?int $grantProposerId = null;
    public ?int $grantSeconderId = null;

    public function openCharityModal(): void
    {
        $this->reset(['grantRecipient', 'grantPurpose', 'grantAmount', 'grantProposerId', 'grantSeconderId']);
        $this->showCharityModal = true;
    }

    public function saveCharityGrantFromMeeting(): void
    {
        $meeting = $this->getMeeting();
        $this->validate([
            'grantRecipient' => 'required|string|max:150',
            'grantPurpose' => 'required|string',
            'grantAmount' => 'required|numeric|min:0.01',
            'grantProposerId' => 'nullable|exists:club_acc_members,id',
            'grantSeconderId' => 'nullable|exists:club_acc_members,id|different:grantProposerId',
        ], [
            'grantSeconderId.different' => 'The Seconder must be a different Brother than the Proposer.',
        ]);

        \App\Domains\ClubAccounting\Models\CharityGrant::create([
            'club_id' => $meeting->club_id,
            'committee_meeting_id' => $meeting->id,
            'recipient_name' => trim($this->grantRecipient),
            'purpose' => trim($this->grantPurpose),
            'amount' => (float) $this->grantAmount,
            'proposer_member_id' => $this->grantProposerId ?: null,
            'seconder_member_id' => $this->grantSeconderId ?: null,
            'approval_status' => \App\Domains\ClubAccounting\Enums\GrantApprovalStatus::Proposed,
            'bacs_reference' => 'BACS-G' . sprintf('%04d', rand(1, 9999)),
        ]);

        $this->showCharityModal = false;
        $this->reset(['grantRecipient', 'grantPurpose', 'grantAmount', 'grantProposerId', 'grantSeconderId']);
        session()->flash('success', 'Charitable donation proposal created and logged in committee meeting.');
    }

    public function updateGrantApprovalStatus(int $grantId, string $status): void
    {
        $meeting = $this->getMeeting();
        $grant = \App\Domains\ClubAccounting\Models\CharityGrant::where('club_id', $meeting->club_id)->findOrFail($grantId);
        $statusEnum = \App\Domains\ClubAccounting\Enums\GrantApprovalStatus::from($status);

        $grant->update([
            'approval_status' => $statusEnum,
            'committee_meeting_id' => $grant->committee_meeting_id ?: $meeting->id,
        ]);

        session()->flash('success', "Charitable grant for '{$grant->recipient_name}' updated to {$statusEnum->label()}.");
    }

    public function updateGrantSeconder(int $grantId, int $seconderId): void
    {
        $meeting = $this->getMeeting();
        $grant = \App\Domains\ClubAccounting\Models\CharityGrant::where('club_id', $meeting->club_id)->findOrFail($grantId);

        if ($grant->proposer_member_id && $grant->proposer_member_id === $seconderId) {
            session()->flash('error', 'The Seconder must be a different Brother than the Proposer.');
            return;
        }

        $grant->update([
            'seconder_member_id' => $seconderId,
            'committee_meeting_id' => $grant->committee_meeting_id ?: $meeting->id,
        ]);

        session()->flash('success', "Seconder assigned for grant proposal '{$grant->recipient_name}'.");
    }

    public function mount(string $clubSlug, int $meetingId): void
    {
        $this->clubSlug = $clubSlug;
        $this->meetingId = $meetingId;
    }

    public function openEditModal(): void
    {
        $meeting = $this->getMeeting();
        $this->editTitle = $meeting->title;
        $this->editDate = $meeting->meeting_date ? $meeting->meeting_date->format('Y-m-d\TH:i') : '';
        $this->editLocation = $meeting->location ?? '';
        $this->editStatus = $meeting->status === CommitteeMeetingStatus::Scheduled ? 'scheduled' : 'draft';
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
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

        $meeting = $this->getMeeting();
        $statusEnum = $this->editStatus === 'scheduled'
            ? CommitteeMeetingStatus::Scheduled
            : CommitteeMeetingStatus::Draft;

        $meeting->update([
            'title' => trim($this->editTitle),
            'meeting_date' => Carbon::parse($this->editDate),
            'location' => trim($this->editLocation) ?: 'Lodge Committee Room',
            'status' => $statusEnum,
        ]);

        $this->showEditModal = false;
        session()->flash('success', 'Committee meeting details updated successfully.');
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

    public function getAvailableAttendees(ClubCommitteeMeeting $meeting)
    {
        $club = $meeting->club;

        // 1. Resolve Roster assignments for this meeting
        $meetingDate = $meeting->meeting_date ? Carbon::parse($meeting->meeting_date) : Carbon::now();
        $year = $meetingDate->format('Y');
        $masonicYear = "{$year}-" . ($meetingDate->year + 1);

        $roster = AnnualOfficerRoster::where('club_id', $club->id)
            ->where(function ($q) use ($masonicYear) {
                $q->where('masonic_year', $masonicYear)
                  ->orWhere('status', 'installed');
            })
            ->orderBy('masonic_year', 'desc')
            ->with(['assignments.member'])
            ->first();

        // Map member_id => committee_role ('chair', 'secretary', 'member')
        $rosterCommitteeMemberRoles = [];
        if ($roster) {
            foreach ($roster->assignments as $assignment) {
                if ($assignment->office === 'committee_member') {
                    $rosterCommitteeMemberRoles[$assignment->member_id] = 'member';
                } elseif ($assignment->office === 'wm') {
                    $rosterCommitteeMemberRoles[$assignment->member_id] = 'chair';
                } elseif ($assignment->office === 'secretary') {
                    $rosterCommitteeMemberRoles[$assignment->member_id] = 'secretary';
                }
            }
        }

        // Map user_id => member
        $clubMembers = Member::where('club_id', $club->id)->get();
        $membersByUserId = $clubMembers->whereNotNull('user_id')->keyBy('user_id');

        $candidates = collect();

        // 2. Add club users
        $users = $club->users()->withPivot('role', 'rank', 'committee_role')->get();
        foreach ($users as $user) {
            $member = $membersByUserId->get($user->id);
            $rosterRole = $member ? ($rosterCommitteeMemberRoles[$member->id] ?? null) : null;

            // Effective committee role: explicit pivot role takes priority, else roster role
            $commRole = $user->pivot->committee_role ?: $rosterRole;

            $candidates->push((object) [
                'id' => (int) $user->id,
                'user_id' => (int) $user->id,
                'member_id' => $member?->id,
                'name' => $user->name,
                'email' => $user->email,
                'committee_role' => $commRole,
                'is_committee' => in_array($commRole, ['chair', 'secretary', 'member']),
            ]);
        }

        // 3. Add lodge members who do not have a linked user account
        foreach ($clubMembers as $member) {
            if ($member->user_id && $users->contains('id', $member->user_id)) {
                continue; // already handled via club user
            }

            $rosterRole = $rosterCommitteeMemberRoles[$member->id] ?? null;

            $candidates->push((object) [
                'id' => 'm_' . $member->id,
                'user_id' => null,
                'member_id' => $member->id,
                'name' => $member->full_name,
                'email' => $member->email ?? '',
                'committee_role' => $rosterRole,
                'is_committee' => in_array($rosterRole, ['chair', 'secretary', 'member']),
            ]);
        }

        return $candidates;
    }

    public function selectAllCommittee(): void
    {
        $meeting = $this->getMeeting();
        $existingAttendees = $meeting->attendees;
        $existingUserIds = $existingAttendees->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->all();
        $existingNames = $existingAttendees->pluck('name')->all();

        $candidates = $this->getAvailableAttendees($meeting);

        $committeeIds = $candidates
            ->filter(fn ($c) => $c->is_committee)
            ->reject(function ($c) use ($existingUserIds, $existingNames) {
                if ($c->user_id && in_array($c->user_id, $existingUserIds)) {
                    return true;
                }
                return in_array($c->name, $existingNames);
            })
            ->map(function ($c) {
                return $c->user_id ? (int) $c->user_id : (string) $c->id;
            })
            ->values()
            ->all();

        $this->selectedMemberIds = array_values(array_unique(array_merge($this->selectedMemberIds, $committeeIds)));
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
        $candidates = $this->getAvailableAttendees($meeting)->keyBy(fn ($c) => (string) $c->id);

        $addedCount = 0;
        foreach ($this->selectedMemberIds as $rawId) {
            $key = (string) $rawId;
            $candidate = $candidates->get($key);

            if (! $candidate && is_numeric($rawId)) {
                $u = User::find($rawId);
                if ($u) {
                    $commRole = $u->clubs()->where('clubs.id', $meeting->club_id)->first()?->pivot->committee_role;
                    $candidate = (object) [
                        'id' => (int) $u->id,
                        'user_id' => (int) $u->id,
                        'name' => $u->name,
                        'committee_role' => $commRole,
                    ];
                }
            }

            if (! $candidate) {
                continue;
            }

            $defaultRole = match ($candidate->committee_role ?? null) {
                'chair' => 'Committee Chair',
                'secretary' => 'Committee Secretary',
                'member' => 'Committee Member',
                default => 'Non-Committee Member',
            };

            $roleTitle = !empty($this->customRoles[$rawId])
                ? trim($this->customRoles[$rawId])
                : (!empty($this->customRoles[$key]) ? trim($this->customRoles[$key]) : $defaultRole);

            if ($candidate->user_id) {
                $attendee = ClubCommitteeAttendee::firstOrCreate([
                    'committee_meeting_id' => $meeting->id,
                    'user_id' => $candidate->user_id,
                ], [
                    'name' => $candidate->name,
                    'role_title' => $roleTitle,
                    'attendance_type' => AttendanceType::Present,
                ]);
            } else {
                $attendee = ClubCommitteeAttendee::firstOrCreate([
                    'committee_meeting_id' => $meeting->id,
                    'name' => $candidate->name,
                ], [
                    'user_id' => null,
                    'role_title' => $roleTitle,
                    'attendance_type' => AttendanceType::Present,
                ]);
            }

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
        $existingAttendeeNames = $meeting->attendees->pluck('name')->all();

        $candidates = $this->getAvailableAttendees($meeting);

        $sorted = $candidates->sortBy(function ($c) {
            $priority = match ($c->committee_role) {
                'chair' => 1,
                'secretary' => 2,
                'member' => 3,
                default => 4,
            };
            return $priority . '_' . strtolower($c->name);
        })->values();

        if (!empty($this->attendeeSearch)) {
            $search = strtolower(trim($this->attendeeSearch));
            $sorted = $sorted->filter(function ($c) use ($search) {
                return str_contains(strtolower($c->name), $search)
                    || str_contains(strtolower($c->email ?? ''), $search)
                    || str_contains(strtolower($c->committee_role ?? ''), $search);
            })->values();
        }

        $committeeMembers = $sorted->filter(fn ($c) => $c->is_committee)->values();
        $nonCommitteeMembers = $sorted->filter(fn ($c) => !$c->is_committee)->values();

        $charityGrants = \App\Domains\ClubAccounting\Models\CharityGrant::where('club_id', $meeting->club_id)
            ->where(function ($q) use ($meeting) {
                $q->where('committee_meeting_id', $meeting->id)
                  ->orWhere('approval_status', \App\Domains\ClubAccounting\Enums\GrantApprovalStatus::Proposed->value);
            })
            ->with(['proposer', 'seconder', 'committeeMeeting'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('livewire.committee.meeting-workspace', array_merge($packData, [
            'club' => $meeting->club,
            'clubMembers' => $sorted,
            'committeeMembers' => $committeeMembers,
            'nonCommitteeMembers' => $nonCommitteeMembers,
            'existingAttendeeUserIds' => $existingAttendeeUserIds,
            'existingAttendeeNames' => $existingAttendeeNames,
            'charityGrants' => $charityGrants,
        ]))->layout('components.layouts.app', [
            'title' => 'Committee Meeting - Agenda Pack',
            'club' => $meeting->club,
        ]);
    }
}
