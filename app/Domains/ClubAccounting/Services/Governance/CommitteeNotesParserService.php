<?php

namespace App\Domains\ClubAccounting\Services\Governance;

use App\Domains\ClubAccounting\Enums\TaskStatus;
use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Domains\ClubAccounting\Models\ClubCommitteeTask;
use App\Domains\ClubAccounting\Models\ClubNoticeOfMotion;
use App\Models\User;
use Carbon\Carbon;

class CommitteeNotesParserService
{
    /**
     * Parse raw notes text to extract @mentions, [ ] tasks, and /motion lines.
     *
     * @return array{mentions: array, tasks: array, motions: array}
     */
    public function parse(string $rawNotes, int $clubId, ?ClubCommitteeMeeting $meeting = null): array
    {
        $clubMembers = User::whereHas('clubs', fn ($q) => $q->where('clubs.id', $clubId))
            ->get(['id', 'name', 'email']);

        $lines = explode("\n", $rawNotes);
        $extractedMentions = [];
        $extractedTasks = [];
        $extractedMotions = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                continue;
            }

            // 1. Detect [ ] or [] or [x] task checkboxes (supports leading bullet or number)
            if (preg_match('/^(?:[-*•]|\d+\.)?\s*\[(?:\s*|x|X)?\]\s*(.*)$/u', $trimmed, $taskMatch)) {
                $taskContent = trim($taskMatch[1]);
                $taskAssignedUser = null;
                $assignedName = null;
                $dueDate = null;

                // Extract due date if present: e.g. "by 2026-10-15" or "due 2026-10-15"
                if (preg_match('/\b(?:by|due|before)\s*[:\-]?\s*(\d{4}-\d{2}-\d{2})\b/i', $taskContent, $dateMatch)) {
                    try {
                        $dueDate = Carbon::parse($dateMatch[1])->format('Y-m-d');
                    } catch (\Exception) {
                        $dueDate = null;
                    }
                }

                // Check for @mention in task text
                // A. Exact or space-stripped match with club members
                foreach ($clubMembers as $member) {
                    $noSpace = str_replace(' ', '', $member->name);
                    if (stripos($taskContent, '@' . $member->name) !== false
                        || stripos($taskContent, '@' . $noSpace) !== false
                        || stripos($taskContent, '@' . $member->email) !== false) {
                        $taskAssignedUser = $member;
                        $assignedName = $member->name;
                        break;
                    }
                }

                // B. If not matched, extract the token directly following @
                if (!$taskAssignedUser && preg_match('/@([A-Za-z0-9_\-\.]+)/u', $taskContent, $tagMatch)) {
                    $rawTag = $tagMatch[1];

                    // Check if rawTag matches any member by first name, last name, or email prefix
                    $matched = $clubMembers->first(function ($m) use ($rawTag) {
                        return stripos($m->name, $rawTag) !== false || stripos($rawTag, $m->name) !== false;
                    });

                    // Also check meeting attendees if available
                    if (!$matched && $meeting && $meeting->attendees) {
                        $attendee = $meeting->attendees->first(function ($att) use ($rawTag) {
                            return stripos($att->name, $rawTag) !== false || stripos($rawTag, $att->name) !== false;
                        });
                        if ($attendee && $attendee->user) {
                            $matched = $attendee->user;
                        } elseif ($attendee) {
                            $assignedName = $attendee->name;
                        }
                    }

                    if ($matched) {
                        $taskAssignedUser = $matched;
                        $assignedName = $matched->name;
                    } else {
                        // Handle mock strings like @MemberName or placeholder @member, @brother, @officer, etc.
                        $fallbackUser = $meeting?->chair ?? $meeting?->secretary ?? $clubMembers->first();
                        $taskAssignedUser = $fallbackUser;
                        $assignedName = $rawTag;
                    }
                }

                $extractedTasks[] = [
                    'title' => $taskContent,
                    'assigned_to_id' => $taskAssignedUser?->id,
                    'assigned_to_name' => $assignedName ?: ($taskAssignedUser?->name ?? 'Unassigned'),
                    'due_date' => $dueDate,
                ];
            }

            // 2. Detect /motion or MOTION: lines (supports leading bullet or number)
            if (preg_match('/^(?:[-*•]|\d+\.)?\s*(?:\/motion|motion:)\s*(.*)$/i', $trimmed, $motionMatch)) {
                $motionText = trim($motionMatch[1]);
                if (!empty($motionText)) {
                    $extractedMotions[] = [
                        'title' => 'Motion: ' . \Illuminate\Support\Str::limit($motionText, 60),
                        'motion_text' => $motionText,
                    ];
                }
            }

            // 3. Detect general @mentions throughout line
            foreach ($clubMembers as $member) {
                if (stripos($trimmed, '@' . $member->name) !== false && !isset($extractedMentions[$member->id])) {
                    $extractedMentions[$member->id] = [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                    ];
                }
            }
        }

        return [
            'mentions' => array_values($extractedMentions),
            'tasks' => $extractedTasks,
            'motions' => $extractedMotions,
        ];
    }

    /**
     * Automatically sync extracted tasks and motions from a meeting's notes_raw into database records.
     */
    public function syncExtractedEntities(ClubCommitteeMeeting $meeting, ?string $rawNotes = null): array
    {
        if ($rawNotes !== null) {
            $raw = $rawNotes;
            if ($meeting->notes_raw !== $rawNotes) {
                $meeting->update([
                    'notes_raw' => $rawNotes,
                    'draft_notes' => $rawNotes,
                ]);
            }
        } else {
            $raw = $meeting->notes_raw ?? '';
        }

        $parsed = $this->parse($raw, $meeting->club_id, $meeting);

        $createdTasks = 0;
        foreach ($parsed['tasks'] as $taskData) {
            try {
                // Check if task with identical title already exists for this meeting
                $exists = ClubCommitteeTask::where('committee_meeting_id', $meeting->id)
                    ->where('title', $taskData['title'])
                    ->exists();

                if (!$exists) {
                    // Ensure assigned_to_user_id exists in users table to prevent FK violations
                    $assignedUserId = null;
                    if (!empty($taskData['assigned_to_id']) && User::where('id', $taskData['assigned_to_id'])->exists()) {
                        $assignedUserId = $taskData['assigned_to_id'];
                    }

                    ClubCommitteeTask::create([
                        'committee_meeting_id' => $meeting->id,
                        'assigned_to_user_id' => $assignedUserId,
                        'assigned_to_name' => $taskData['assigned_to_name'],
                        'title' => $taskData['title'],
                        'due_date' => $taskData['due_date'],
                        'status' => TaskStatus::Pending,
                    ]);
                    $createdTasks++;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to sync committee task: ' . $e->getMessage());
            }
        }

        $createdMotions = 0;
        foreach ($parsed['motions'] as $motionData) {
            try {
                $exists = ClubNoticeOfMotion::where('committee_meeting_id', $meeting->id)
                    ->where('motion_text', $motionData['motion_text'])
                    ->exists();

                if (!$exists) {
                    $proposerId = null;
                    if ($meeting->chair_user_id && User::where('id', $meeting->chair_user_id)->exists()) {
                        $proposerId = $meeting->chair_user_id;
                    }

                    ClubNoticeOfMotion::create([
                        'club_id' => $meeting->club_id,
                        'committee_meeting_id' => $meeting->id,
                        'proposer_user_id' => $proposerId,
                        'proposer_name' => $meeting->chair?->name ?? 'Committee Chair',
                        'title' => $motionData['title'],
                        'motion_text' => $motionData['motion_text'],
                        'status' => 'draft_committee',
                    ]);
                    $createdMotions++;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to sync committee motion: ' . $e->getMessage());
            }
        }

        return [
            'tasks_created' => $createdTasks,
            'motions_created' => $createdMotions,
            'tasks_count' => $createdTasks,
            'motions_count' => $createdMotions,
            'total_parsed_tasks' => count($parsed['tasks']),
            'total_parsed_motions' => count($parsed['motions']),
        ];
    }

    /**
     * Alias for syncExtractedEntities.
     */
    public function extractEntities(ClubCommitteeMeeting $meeting, ?string $rawNotes = null): array
    {
        return $this->syncExtractedEntities($meeting, $rawNotes);
    }

    private function findMemberByName(string $nameQuery, $clubMembers): ?User
    {
        $clean = strtolower(trim($nameQuery));
        if (empty($clean)) {
            return null;
        }

        // Exact match
        $found = $clubMembers->first(fn ($u) => strtolower($u->name) === $clean);
        if ($found) {
            return $found;
        }

        // Substring match
        return $clubMembers->first(fn ($u) => str_contains(strtolower($u->name), $clean));
    }
}
