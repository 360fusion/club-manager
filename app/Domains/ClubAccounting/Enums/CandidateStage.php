<?php

namespace App\Domains\ClubAccounting\Enums;

/**
 * Where a candidate is between the first enquiry and initiation. The first six are the working stages shown on the
 * board; Initiated, Rejected and Withdrawn (shown as "Closed") are where a candidate ends up.
 */
enum CandidateStage: string
{
    case Enquiry = 'enquiry';
    case FirstInterview = 'first_interview';
    case InterviewPending = 'interview_pending';
    case LodgeCommittee = 'lodge_committee';
    case Proposed = 'proposed';
    case Accepted = 'accepted';
    case Initiated = 'initiated';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Enquiry => 'Enquiry',
            self::FirstInterview => 'First interview',
            self::InterviewPending => 'Pending: informal meeting',
            self::LodgeCommittee => 'Lodge committee',
            self::Proposed => 'Proposed: awaiting ballot',
            self::Accepted => 'Balloted and accepted',
            self::Initiated => 'Initiated',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Closed',
        };
    }

    /**
     * A short column heading for the board.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::InterviewPending => 'Pending',
            self::LodgeCommittee => 'Committee',
            self::Proposed => 'Proposed',
            self::Accepted => 'Accepted',
            default => $this->label(),
        };
    }

    /**
     * What normally happens in this stage, shown as a prompt.
     */
    public function hint(): string
    {
        return match ($this) {
            self::Enquiry => 'Acknowledge the enquiry and arrange a phone call.',
            self::FirstInterview => 'Phone call. Note how it went and decide whether to go on.',
            self::InterviewPending => 'Informal meeting over coffee or a pint. Choose the proposer and seconder.',
            self::LodgeCommittee => 'Meet the lodge committee and record their recommendation.',
            self::Proposed => 'Form P to the Province, proposed at a regular meeting, ballot at the next.',
            self::Accepted => 'Balloted successfully. Arrange the initiation within a year.',
            self::Initiated => 'Initiated and now a member.',
            self::Rejected => 'Not proceeding.',
            self::Withdrawn => 'Enquiry closed.',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Enquiry => 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-100 border-slate-300 dark:border-slate-700',
            self::FirstInterview => 'bg-sky-50 dark:bg-sky-950/40 text-sky-800 dark:text-sky-200 border-sky-200 dark:border-sky-800/60',
            self::InterviewPending => 'bg-blue-50 dark:bg-blue-950/40 text-blue-800 dark:text-blue-200 border-blue-200 dark:border-blue-800/60',
            self::LodgeCommittee => 'bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-200 border-amber-300 dark:border-amber-700/60',
            self::Proposed => 'bg-violet-50 dark:bg-violet-950/40 text-violet-800 dark:text-violet-200 border-violet-300 dark:border-violet-700/60',
            self::Accepted => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 border-emerald-300 dark:border-emerald-700/60',
            self::Initiated => 'bg-blue-50 dark:bg-blue-950/40 text-blue-800 dark:text-blue-200 border-blue-300 dark:border-blue-700/60',
            self::Rejected => 'bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-200 border-rose-300 dark:border-rose-700/60',
            self::Withdrawn => 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300 border-gray-300 dark:border-slate-700',
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::Enquiry => 1,
            self::FirstInterview => 2,
            self::InterviewPending => 3,
            self::LodgeCommittee => 4,
            self::Proposed => 5,
            self::Accepted => 6,
            self::Initiated => 7,
            self::Rejected => 8,
            self::Withdrawn => 9,
        };
    }

    /**
     * Still moving through the process (a place on the board).
     */
    public function isActive(): bool
    {
        return $this->order() <= 6;
    }

    /**
     * Left the process without being initiated.
     */
    public function isClosed(): bool
    {
        return in_array($this, [self::Rejected, self::Withdrawn], true);
    }

    /**
     * The stages that make up the board, left to right.
     *
     * @return list<self>
     */
    public static function board(): array
    {
        return array_values(array_filter(self::cases(), fn (self $stage) => $stage->isActive()));
    }

    /**
     * The next step forward, or null at the end.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::Enquiry => self::FirstInterview,
            self::FirstInterview => self::InterviewPending,
            self::InterviewPending => self::LodgeCommittee,
            self::LodgeCommittee => self::Proposed,
            self::Proposed => self::Accepted,
            self::Accepted => self::Initiated,
            default => null,
        };
    }
}
