<?php

namespace App\Domains\ClubAccounting\Livewire\Committee;

use App\Domains\ClubAccounting\Enums\AttendanceType;
use App\Domains\ClubAccounting\Enums\CommitteeMeetingStatus;
use App\Domains\ClubAccounting\Enums\GrantApprovalStatus;
use App\Domains\ClubAccounting\Enums\TaskStatus;
use App\Domains\ClubAccounting\Models\CharityGrant;
use App\Domains\ClubAccounting\Models\ClubCommitteeAgendaItem;
use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Domains\ClubAccounting\Models\ClubCommitteeTask;
use App\Domains\ClubAccounting\Models\ClubNoticeOfMotion;
use App\Domains\ClubAccounting\Notifications\CommitteeTaskAssignedNotification;
use App\Domains\ClubAccounting\Services\CommitteeNotesParserService;
use App\Domains\ClubAccounting\Services\Integration\MemberMentionSearchService;
use App\Models\Club;
use App\Support\Currencies;
use Carbon\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Component;

class LiveMinuteTaker extends Component
{
    #[Locked]
    public string $clubSlug;

    #[Locked]
    public int $meetingId;

    public ?ClubCommitteeMeeting $meeting = null;

    public string $notesRaw = '';

    public ?string $content = null;

    public ?string $notes = null;

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

        $this->meeting = $this->getMeeting();
        $this->notesRaw = $this->meeting->notes_raw ?? '';
        $this->content = $this->notesRaw;
        $this->notes = $this->notesRaw;
        $this->lastSavedAt = $this->meeting->updated_at ? $this->meeting->updated_at->format('H:i:s') : 'Never';
        $this->updateParsedPreview();
    }

    public function updatedNotesRaw(): void
    {
        $this->content = $this->notesRaw;
        $this->notes = $this->notesRaw;
        $this->autoSave();
        $this->updateParsedPreview();
    }

    public function updatedContent(): void
    {
        $this->notesRaw = $this->content ?? '';
        $this->notes = $this->content ?? '';
        $this->autoSave();
        $this->updateParsedPreview();
    }

    public function autoSave(): void
    {
        $meeting = $this->meeting ?: $this->getMeeting();
        $text = $this->content ?? $this->notesRaw;
        $this->notesRaw = $text;
        $this->content = $text;
        $this->notes = $text;

        $meeting->update([
            'notes_raw' => $text,
            'draft_notes' => $text,
            'status' => $meeting->status === CommitteeMeetingStatus::Scheduled
                ? CommitteeMeetingStatus::InProgress
                : $meeting->status,
        ]);

        $this->lastSavedAt = Carbon::now()->format('H:i:s');
    }

    public function updateParsedPreview(): void
    {
        $meeting = $this->meeting ?: $this->getMeeting();
        $parser = app(CommitteeNotesParserService::class);
        $this->parsedPreview = $parser->parse($this->content ?? $this->notesRaw, $meeting->club_id, $meeting);
    }

    public function commitDetectedItems(?string $editorContent = null): void
    {
        if (! $this->meeting) {
            $this->meeting = $this->getMeeting();
        }

        // Sync content directly from client if passed
        if ($editorContent !== null) {
            $this->content = $editorContent;
            $this->notesRaw = $editorContent;
            $this->notes = $editorContent;
            $this->meeting->update(['draft_notes' => $editorContent]);
        } else {
            if ($this->content === null) {
                $this->content = $this->notesRaw;
            }
        }

        $parser = app(CommitteeNotesParserService::class);
        $results = $parser->extractEntities($this->meeting, $this->content);

        // Switch to the 'tasks' tab so the user visually sees the committed action items immediately
        $this->activeRightTab = 'tasks';

        // Refresh relations so Livewire re-renders the right sidebar and counters
        $this->meeting->load(['tasks', 'tasks.assignedTo', 'noticesOfMotion']);

        // Update parsed preview
        $this->updateParsedPreview();

        $message = "Successfully committed {$results['tasks_count']} tasks and {$results['motions_count']} motions.";
        session()->flash('success', $message);

        // Emit notification event for toast feedback
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $message,
            'tasks_count' => $results['tasks_count'],
            'motions_count' => $results['motions_count'],
        ]);
    }

    public function syncExtractedEntities(?string $editorContent = null): void
    {
        $this->commitDetectedItems($editorContent);
    }

    public function syncEntities(?string $editorContent = null): void
    {
        $this->commitDetectedItems($editorContent);
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

    public function approveGrantForSummons(int $grantId): void
    {
        $grant = CharityGrant::where('club_id', $this->meeting->club_id)->findOrFail($grantId);
        $grant->update([
            'approval_status' => GrantApprovalStatus::CommitteeApproved,
            'committee_meeting_id' => $this->meetingId,
        ]);

        $proposerName = $grant->proposer ? " (Proposed by {$grant->proposer->formatted_rank_name})" : '';
        $seconderName = $grant->seconder ? " (Seconded by {$grant->seconder->formatted_rank_name})" : '';
        $noteSnippet = "\n- **Charitable Grant Approved by Committee:** ".Currencies::format($grant->amount, $this->meeting->club)." to {$grant->recipient_name} ({$grant->purpose}){$proposerName}{$seconderName}. Recommended for Open Lodge sanction.\n";

        $this->notesRaw .= $noteSnippet;
        $this->updatedNotesRaw();

        session()->flash('success', 'Charitable grant of '.Currencies::format($grant->amount, $this->meeting->club)." to {$grant->recipient_name} approved for Open Lodge Summons.");
    }

    public function lodgeVoteGrant(int $grantId): void
    {
        $grant = CharityGrant::where('club_id', $this->meeting->club_id)->findOrFail($grantId);
        $grant->update([
            'approval_status' => GrantApprovalStatus::LodgeVoted,
            'committee_meeting_id' => $this->meetingId,
        ]);

        session()->flash('success', "Charitable grant to {$grant->recipient_name} marked as Open Lodge Voted.");
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
        $this->notesRaw .= ' '.$mentionTag.' ';
        $this->memberQuery = '';
        $this->mentionSuggestions = [];
        $this->updatedNotesRaw();
    }

    public function insertTemplate(string $type): void
    {
        $symbol = Currencies::symbolFor($this->meeting->club);
        $snippet = match ($type) {
            'task' => "\n[ ] @MemberName Action to be completed by ".Carbon::now()->addDays(14)->format('Y-m-d')."\n",
            'motion' => "\n/motion That the lodge bylaws be amended to specify...\n",
            'candidate' => "\n### Candidate Vetting:\n- Candidate: @CandidateName\n- Proposer / Seconder check: Vetted and in order under Rule 159.\n- Recommendation: Approved to proceed to open lodge ballot.\n",
            'donation' => "\n### Charitable Donation Proposal:\n- Recipient Name: Local Hospice\n- Proposed Amount: {$symbol}250.00\n- Proposed By: @ProposerName\n- Seconded By: @SeconderName\n- Committee Recommendation: Approved by committee and recommended for open lodge sanction.\n",
            'audit' => "\n### Accounts & Bill Audit (Rule 158):\n- Audited invoices: Catering bill and hall rental confirmed against receipts.\n- Recommendation: Approved for payment by the Treasurer.\n",
            default => '',
        };

        $this->notesRaw .= $snippet;
        $this->updatedNotesRaw();
    }

    public function toggleAgendaApproval(int $itemId): void
    {
        $item = ClubCommitteeAgendaItem::where('committee_meeting_id', $this->meetingId)->findOrFail($itemId);
        $item->update(['is_approved' => ! $item->is_approved]);
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
        $outline .= '**Date:** '.($meeting->meeting_date ? $meeting->meeting_date->format('jS F Y, H:i') : 'TBD')."\n\n";

        // Attendees Roll Call summary
        $attendees = $meeting->attendees;
        if ($attendees->isNotEmpty()) {
            $present = $attendees->where('attendance_type', AttendanceType::Present)->pluck('name')->implode(', ');
            $apologies = $attendees->where('attendance_type', AttendanceType::Apology)->pluck('name')->implode(', ');
            $outline .= '**Present:** '.($present ?: 'None recorded')."\n";
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

        $this->notesRaw = trim($this->notesRaw) ? $this->notesRaw."\n\n".$outline : $outline;
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
                'noticesOfMotion',
            ])
            ->firstOrFail();
    }

    public function render()
    {
        $meeting = $this->getMeeting();

        $charityGrants = CharityGrant::where('club_id', $meeting->club_id)
            ->with(['proposer', 'seconder'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('livewire.committee.live-minute-taker', [
            'meeting' => $meeting,
            'club' => $meeting->club,
            'agendaItems' => $meeting->agendaItems,
            'attendees' => $meeting->attendees,
            'tasks' => $meeting->tasks,
            'motions' => $meeting->noticesOfMotion,
            'charityGrants' => $charityGrants,
        ])->layout('components.layouts.app', [
            'title' => $meeting->title.' — Live Minutes',
            'club' => $meeting->club,
        ]);
    }
}
