<?php

namespace App\Domains\ClubAccounting\Livewire\Committee;

use App\Domains\ClubAccounting\Enums\AttendanceType;
use App\Domains\ClubAccounting\Enums\CommitteeMeetingStatus;
use App\Domains\ClubAccounting\Enums\TaskStatus;
use App\Domains\ClubAccounting\Models\ClubCommitteeAgendaItem;
use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Domains\ClubAccounting\Models\ClubCommitteeTask;
use App\Domains\ClubAccounting\Models\ClubNoticeOfMotion;
use App\Domains\ClubAccounting\Notifications\CommitteeTaskAssignedNotification;
use App\Domains\ClubAccounting\Services\Governance\CommitteeNotesParserService;
use App\Domains\ClubAccounting\Services\Integration\MemberMentionSearchService;
use App\Models\Club;
use Carbon\Carbon;
use Livewire\Component;

class LiveMinuteTaker extends Component
{
    public string $clubSlug;
    public int $meetingId;
    public string $notesRaw = '';
    public string $lastSavedAt = '';

    // Active Tab in Right Sidebar ('live', 'tasks', 'motions')
    public string $activeRightTab = 'live';

    // Extracted live preview
    public array $parsedPreview = ['mentions' => [], 'tasks' => [], 'motions' => []];

    // Member search for autocomplete
    public string $memberQuery = '';
    public array $mentionSuggestions = [];

    public function mount(string $clubSlug, int $meetingId): void
    {
        $this->clubSlug = $clubSlug;
        $this->meetingId = $meetingId;

        $meeting = $this->getMeeting();
        $this->notesRaw = $meeting->notes_raw ?? '';
        $this->lastSavedAt = $meeting->updated_at ? $meeting->updated_at->format('H:i:s') : 'Never';
        $this->updateParsedPreview();
    }

    public function updatedNotesRaw(): void
    {
        $this->autoSave();
        $this->updateParsedPreview();
    }

    public function autoSave(): void
    {
        $meeting = $this->getMeeting();
        $meeting->update([
            'notes_raw' => $this->notesRaw,
            'status' => $meeting->status === CommitteeMeetingStatus::Scheduled
                ? CommitteeMeetingStatus::InProgress
                : $meeting->status,
        ]);

        $this->lastSavedAt = Carbon::now()->format('H:i:s');
    }

    public function updateParsedPreview(): void
    {
        $meeting = $this->getMeeting();
        $parser = app(CommitteeNotesParserService::class);
        $this->parsedPreview = $parser->parse($this->notesRaw, $meeting->club_id);
    }

    public function syncEntities(): void
    {
        $this->autoSave();
        $meeting = $this->getMeeting();
        $parser = app(CommitteeNotesParserService::class);
        $result = $parser->syncExtractedEntities($meeting);

        session()->flash('success', "Synced: {$result['tasks_created']} tasks and {$result['motions_created']} motions created.");
    }

    public function toggleTaskStatus(int $taskId): void
    {
        $task = ClubCommitteeTask::where('committee_meeting_id', $this->meetingId)->findOrFail($taskId);
        $newStatus = $task->status === TaskStatus::Completed ? TaskStatus::Pending : TaskStatus::Completed;

        $task->update([
            'status' => $newStatus,
            'completed_at' => $newStatus === TaskStatus::Completed ? Carbon::now() : null,
        ]);
    }

    public function notifyAssignedMember(int $taskId): void
    {
        $task = ClubCommitteeTask::where('committee_meeting_id', $this->meetingId)->findOrFail($taskId);
        if ($task->assignedTo) {
            $task->assignedTo->notify(new CommitteeTaskAssignedNotification($task));
            session()->flash('success', "Task notification dispatched to {$task->assignedTo->name}.");
        } else {
            session()->flash('error', 'Cannot notify: No registered member is assigned to this task.');
        }
    }

    public function approveMotionForSummons(int $motionId): void
    {
        $motion = ClubNoticeOfMotion::where('committee_meeting_id', $this->meetingId)->findOrFail($motionId);
        $motion->update(['status' => 'approved_for_summons']);

        session()->flash('success', "Motion '{$motion->title}' approved for open lodge summons.");
    }

    public function finalizeMinutes(): void
    {
        $this->autoSave();
        $this->syncEntities();

        $meeting = $this->getMeeting();
        $meeting->update([
            'status' => CommitteeMeetingStatus::Finalized,
            'finalized_at' => Carbon::now(),
            'minutes_final' => $this->notesRaw,
        ]);

        session()->flash('success', 'Committee minutes have been finalized and recorded in the permanent lodge archives.');
    }

    public function updatedMemberQuery(): void
    {
        if (strlen($this->memberQuery) >= 2) {
            $meeting = $this->getMeeting();
            $searchService = app(MemberMentionSearchService::class);
            $this->mentionSuggestions = $searchService->search($meeting->club_id, $this->memberQuery, 5)->toArray();
        } else {
            $this->mentionSuggestions = [];
        }
    }

    public function insertMention(string $mentionTag): void
    {
        $this->notesRaw .= ' ' . $mentionTag . ' ';
        $this->memberQuery = '';
        $this->mentionSuggestions = [];
        $this->updatedNotesRaw();
    }

    public function insertTemplate(string $type): void
    {
        $snippet = match ($type) {
            'task' => "\n[ ] @MemberName Action to be completed by " . Carbon::now()->addDays(14)->format('Y-m-d') . "\n",
            'motion' => "\n/motion That the lodge bylaws be amended to specify...\n",
            'candidate' => "\n### Candidate Vetting:\n- Candidate: @CandidateName\n- Proposer / Seconder check: Vetted and in order under Rule 159.\n- Recommendation: Approved to proceed to open lodge ballot.\n",
            'audit' => "\n### Accounts & Bill Audit (Rule 158):\n- Audited invoices: Catering bill and hall rental confirmed against receipts.\n- Recommendation: Approved for payment by the Treasurer.\n",
            default => '',
        };

        $this->notesRaw .= $snippet;
        $this->updatedNotesRaw();
    }

    public function toggleAgendaApproval(int $itemId): void
    {
        $item = ClubCommitteeAgendaItem::where('committee_meeting_id', $this->meetingId)->findOrFail($itemId);
        $item->update(['is_approved' => !$item->is_approved]);
    }

    public function insertAgendaItem(int $itemId): void
    {
        $item = ClubCommitteeAgendaItem::where('committee_meeting_id', $this->meetingId)->findOrFail($itemId);
        $typeLabel = $item->item_type?->label() ?? 'General Business';
        $snippet = "\n\n### {$item->order}. {$item->title} [{$typeLabel}]\n";
        if ($item->description) {
            $snippet .= "{$item->description}\n";
        }
        if ($item->recommendation_text) {
            $snippet .= "**Committee Recommendation:** {$item->recommendation_text}\n";
        }
        $snippet .= "- Proceedings & Notes: \n";

        $this->notesRaw .= $snippet;
        $this->updatedNotesRaw();
        session()->flash('success', "Inserted heading for Agenda Item #{$item->order}.");
    }

    public function loadAgendaOutline(): void
    {
        $meeting = $this->getMeeting();
        $items = $meeting->agendaItems;

        $outline = "# Meeting Minutes: {$meeting->title}\n";
        $outline .= "**Date:** " . ($meeting->meeting_date ? $meeting->meeting_date->format('jS F Y, H:i') : 'TBD') . "\n\n";

        // Attendees Roll Call summary
        $attendees = $meeting->attendees;
        if ($attendees->isNotEmpty()) {
            $present = $attendees->where('attendance_type', AttendanceType::Present)->pluck('name')->implode(', ');
            $apologies = $attendees->where('attendance_type', AttendanceType::Apology)->pluck('name')->implode(', ');
            $outline .= "**Present:** " . ($present ?: 'None recorded') . "\n";
            if ($apologies) {
                $outline .= "**Apologies for Absence:** {$apologies}\n";
            }
            $outline .= "\n---\n\n";
        }

        foreach ($items as $item) {
            $typeLabel = $item->item_type?->label() ?? 'General Business';
            $outline .= "### {$item->order}. {$item->title} [{$typeLabel}]\n";
            if ($item->description) {
                $outline .= "{$item->description}\n";
            }
            if ($item->recommendation_text) {
                $outline .= "**Recommendation:** {$item->recommendation_text}\n";
            }
            $outline .= "- Proceedings: \n\n";
        }

        $this->notesRaw = trim($this->notesRaw) ? $this->notesRaw . "\n\n" . $outline : $outline;
        $this->updatedNotesRaw();
        session()->flash('success', 'Agenda outline loaded into meeting minutes.');
    }

    private function getMeeting(): ClubCommitteeMeeting
    {
        $club = Club::where('slug', $this->clubSlug)->firstOrFail();
        return ClubCommitteeMeeting::where('club_id', $club->id)
            ->where('id', $this->meetingId)
            ->with([
                'club',
                'chair',
                'secretary',
                'attendees',
                'agendaItems' => fn ($q) => $q->orderBy('order'),
                'tasks.assignedTo',
                'noticesOfMotion'
            ])
            ->firstOrFail();
    }

    public function render()
    {
        $meeting = $this->getMeeting();

        return view('livewire.committee.live-minute-taker', [
            'meeting' => $meeting,
            'club' => $meeting->club,
            'agendaItems' => $meeting->agendaItems,
            'attendees' => $meeting->attendees,
            'tasks' => $meeting->tasks,
            'motions' => $meeting->noticesOfMotion,
        ])->layout('components.layouts.app');
    }
}
