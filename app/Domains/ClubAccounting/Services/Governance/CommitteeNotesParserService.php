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
    public function parse(string $rawNotes, int $clubId): array
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

            // 1. Detect [ ] or [] or [x] task checkboxes
            if (preg_match('/^\[(?:\s*|x|X)?\]\s*(.*)$/u', $trimmed, $taskMatch)) {
                $taskContent = trim($taskMatch[1]);
                $taskAssignedUser = null;
                $dueDate = null;

                // Extract due date if present: e.g. "by 2026-10-15" or "due 2026-10-15"
                if (preg_match('/\b(?:by|due|before)\s*[:\-]?\s*(\d{4}-\d{2}-\d{2})\b/i', $taskContent, $dateMatch)) {
                    try {
                        $dueDate = Carbon::parse($dateMatch[1])->format('Y-m-d');
                    } catch (\Exception) {
                        $dueDate = null;
                    }
                }

                // Check for @mention in task text by scanning club members
                foreach ($clubMembers as $member) {
                    if (stripos($taskContent, '@' . $member->name) !== false) {
                        $taskAssignedUser = $member;
                        break;
                    }
                }

                $extractedTasks[] = [
                    'title' => $taskContent,
                    'assigned_to_id' => $taskAssignedUser?->id,
                    'assigned_to_name' => $taskAssignedUser?->name,
                    'due_date' => $dueDate,
                ];
            }

            // 2. Detect /motion or MOTION: lines
            if (preg_match('/^(?:\/motion|motion:)\s*(.*)$/i', $trimmed, $motionMatch)) {
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
    public function syncExtractedEntities(ClubCommitteeMeeting $meeting): array
    {
        $raw = $meeting->notes_raw ?? '';
        $parsed = $this->parse($raw, $meeting->club_id);

        $createdTasks = 0;
        foreach ($parsed['tasks'] as $taskData) {
            // Check if task with identical title already exists for this meeting
            $exists = ClubCommitteeTask::where('committee_meeting_id', $meeting->id)
                ->where('title', $taskData['title'])
                ->exists();

            if (!$exists) {
                ClubCommitteeTask::create([
                    'committee_meeting_id' => $meeting->id,
                    'assigned_to_user_id' => $taskData['assigned_to_id'],
                    'assigned_to_name' => $taskData['assigned_to_name'],
                    'title' => $taskData['title'],
                    'due_date' => $taskData['due_date'],
                    'status' => TaskStatus::Pending,
                ]);
                $createdTasks++;
            }
        }

        $createdMotions = 0;
        foreach ($parsed['motions'] as $motionData) {
            $exists = ClubNoticeOfMotion::where('committee_meeting_id', $meeting->id)
                ->where('motion_text', $motionData['motion_text'])
                ->exists();

            if (!$exists) {
                ClubNoticeOfMotion::create([
                    'club_id' => $meeting->club_id,
                    'committee_meeting_id' => $meeting->id,
                    'proposer_user_id' => $meeting->chair_user_id,
                    'proposer_name' => $meeting->chair?->name,
                    'title' => $motionData['title'],
                    'motion_text' => $motionData['motion_text'],
                    'status' => 'draft_committee',
                ]);
                $createdMotions++;
            }
        }

        return [
            'tasks_created' => $createdTasks,
            'motions_created' => $createdMotions,
            'total_parsed_tasks' => count($parsed['tasks']),
            'total_parsed_motions' => count($parsed['motions']),
        ];
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
