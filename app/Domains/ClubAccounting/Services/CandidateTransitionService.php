<?php

namespace App\Domains\ClubAccounting\Services;

use App\Domains\ClubAccounting\Enums\CandidateStage;
use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Models\Candidate;
use App\Domains\ClubAccounting\Models\CandidateEvent;
use App\Domains\ClubAccounting\Models\Member;
use App\Models\User;
use App\Support\ClubAccess;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The rules for moving a candidate from enquiry to initiation, and for ending an enquiry. Every change is written to
 * the candidate's history with who did it and why, and the history is never rewritten.
 *
 * The normal path is one step forward at a time. Skipping a stage or going back needs a reason, and rejecting or closing
 * always needs a reason from the lists below plus a short note.
 */
class CandidateTransitionService
{
    public const REJECT_REASONS = [
        'not_suitable' => 'Not suitable after interview',
        'not_eligible' => 'Not eligible',
        'declined_by_committee' => 'Declined by the committee',
        'failed_ballot' => 'Not elected at the ballot',
        'clearance_not_obtained' => 'Clearance not obtained',
        'other' => 'Other',
    ];

    public const CLOSE_REASONS = [
        'withdrew' => 'Candidate withdrew',
        'no_response' => 'No response',
        'joined_another_lodge' => 'Joined another lodge',
        'moved_away' => 'Moved away',
        'not_ready' => 'Not ready, revisit later',
        'proposal_lapsed' => 'Proposal lapsed',
        'initiation_window_passed' => 'Initiation window passed',
        'other' => 'Other',
    ];

    /**
     * Move a candidate to another stage, checking what that stage needs first.
     *
     * @param  array<string, mixed>  $attributes  details entered along the way (proposer, dates and so on)
     * @param  string|null  $reason  required to skip ahead or go back
     */
    public function transitionStage(Candidate $candidate, CandidateStage|string $newStage, array $attributes = [], ?User $actor = null, ?string $reason = null): Candidate
    {
        $target = $newStage instanceof CandidateStage ? $newStage : CandidateStage::from($newStage);
        $from = $candidate->stage;
        $reason = trim((string) $reason);

        if ($target->isClosed()) {
            throw new InvalidArgumentException('Use reject or close to end an enquiry, so the reason is recorded.');
        }

        if (! $candidate->isActive()) {
            throw new InvalidArgumentException($from === CandidateStage::Initiated ? 'This candidate has already been initiated.' : 'This enquiry has ended. Reopen it first.');
        }

        if ($target === $from) {
            return $candidate;
        }

        $steps = $target->order() - $from->order();

        if ($steps < 0 && $reason === '') {
            throw new InvalidArgumentException('Going back a stage needs a reason.');
        }

        if ($steps > 1 && $reason === '') {
            throw new InvalidArgumentException('Skipping a stage needs a reason, for example a joining member who does not need the interviews.');
        }

        return DB::transaction(function () use ($candidate, $target, $from, $attributes, $actor, $reason, $steps) {
            if ($attributes !== []) {
                $candidate->fill($attributes);
            }

            $this->assertReady($candidate, $target, $attributes);

            if ($target === CandidateStage::Initiated) {
                $initDate = isset($attributes['initiation_date']) ? Carbon::parse($attributes['initiation_date']) : ($candidate->initiation_date ?? Carbon::now());
                $this->convertCandidateToMember($candidate, $initDate, $actor, $reason ?: null);

                return $candidate->fresh();
            }

            $candidate->stage = $target;
            $candidate->stage_entered_at = now();
            $candidate->on_hold_at = null;
            $candidate->on_hold_until = null;
            $candidate->save();

            $why = $reason !== '' ? ($steps < 0 ? ' (back a stage: ' : ' (skipped ahead: ').$reason.')' : '';
            $this->log($candidate, $actor, 'stage_change', 'Moved from '.$from->label().' to '.$target->label().$why, $from, $target);

            return $candidate->fresh();
        });
    }

    /**
     * What has to be in place before a candidate can be in this stage.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function assertReady(Candidate $candidate, CandidateStage $target, array $attributes): void
    {
        switch ($target) {
            case CandidateStage::LodgeCommittee:
                if (! $candidate->proposer_member_id || ! $candidate->seconder_member_id) {
                    throw new InvalidArgumentException('Candidate requires both a proposer and seconder before moving to Lodge Committee.');
                }

                if ($candidate->proposer_member_id === $candidate->seconder_member_id) {
                    throw new InvalidArgumentException('The proposer and seconder must be different members.');
                }
                break;

            case CandidateStage::Proposed:
                $missing = array_filter([
                    $candidate->committee_recommended_at ? null : 'the committee\'s recommendation',
                    $candidate->form_p_signed_at ? null : 'the date Form P was signed',
                    $candidate->proposed_at ? null : 'the date proposed in open lodge',
                ]);

                if ($missing !== []) {
                    throw new InvalidArgumentException('Before Proposed, record '.implode(', ', $missing).'.');
                }
                break;

            case CandidateStage::Accepted:
                if ($candidate->ballot_result !== 'elected' || ! $candidate->ballot_at) {
                    throw new InvalidArgumentException('Record the ballot result first. Being elected moves the candidate to Accepted.');
                }
                break;

            case CandidateStage::Initiated:
                $initDate = isset($attributes['initiation_date']) ? Carbon::parse($attributes['initiation_date']) : ($candidate->initiation_date ?? Carbon::now());

                if ($candidate->ballot_at && $initDate->lt($candidate->ballot_at)) {
                    throw new InvalidArgumentException('The initiation cannot be before the ballot ('.$candidate->ballot_at->format('j M Y').').');
                }

                if ($candidate->initiate_by && $initDate->gt($candidate->initiate_by)) {
                    throw new InvalidArgumentException('The year allowed for initiation ended on '.$candidate->initiate_by->format('j M Y').'. The candidate needs to be proposed again.');
                }
                break;

            default:
                break;
        }
    }

    /**
     * End an enquiry because the lodge or the ballot decided against the candidate.
     */
    public function reject(Candidate $candidate, ?User $actor, string $reasonCode, string $note): Candidate
    {
        return $this->end($candidate, $actor, CandidateStage::Rejected, 'rejected', 'reject', self::REJECT_REASONS, $reasonCode, $note);
    }

    /**
     * End an enquiry without a decision against the candidate (withdrew, no reply, joined elsewhere and so on).
     */
    public function close(Candidate $candidate, ?User $actor, string $reasonCode, string $note): Candidate
    {
        return $this->end($candidate, $actor, CandidateStage::Withdrawn, 'closed', 'close', self::CLOSE_REASONS, $reasonCode, $note);
    }

    /**
     * @param  array<string, string>  $reasons
     */
    private function end(Candidate $candidate, ?User $actor, CandidateStage $stage, string $outcome, string $type, array $reasons, string $reasonCode, string $note): Candidate
    {
        $note = trim($note);

        if (! isset($reasons[$reasonCode])) {
            throw new InvalidArgumentException('Choose a reason from the list.');
        }

        if (mb_strlen($note) < 3) {
            throw new InvalidArgumentException('Add a short note explaining why, so the history makes sense later.');
        }

        if (! $candidate->isActive()) {
            throw new InvalidArgumentException($candidate->stage === CandidateStage::Initiated ? 'This candidate has already been initiated.' : 'This enquiry has already ended.');
        }

        return DB::transaction(function () use ($candidate, $actor, $stage, $outcome, $type, $reasons, $reasonCode, $note) {
            $from = $candidate->stage;

            $candidate->update([
                'stage' => $stage,
                'stage_entered_at' => now(),
                'outcome' => $outcome,
                'outcome_reason' => $reasonCode,
                'outcome_note' => $note,
                'outcome_at' => now(),
                'outcome_by' => $actor?->id,
                'outcome_from_stage' => $from->value,
                'on_hold_at' => null,
                'on_hold_until' => null,
            ]);

            $this->log($candidate, $actor, $type, ($type === 'reject' ? 'Rejected' : 'Closed').': '.$reasons[$reasonCode], $from, $stage, $note);

            return $candidate->fresh();
        });
    }

    /**
     * Put an enquiry on hold (waiting for the candidate, or a later date) without ending it.
     */
    public function hold(Candidate $candidate, ?User $actor, ?Carbon $until, string $note): Candidate
    {
        if (! $candidate->isActive()) {
            throw new InvalidArgumentException('Only an active enquiry can be put on hold.');
        }

        $candidate->update(['on_hold_at' => now(), 'on_hold_until' => $until]);
        $this->log($candidate, $actor, 'hold', 'Put on hold'.($until ? ' until '.$until->format('j M Y') : ''), null, null, trim($note) ?: null);

        return $candidate->fresh();
    }

    public function resume(Candidate $candidate, ?User $actor): Candidate
    {
        $candidate->update(['on_hold_at' => null, 'on_hold_until' => null]);
        $this->log($candidate, $actor, 'hold', 'Taken off hold');

        return $candidate->fresh();
    }

    /**
     * Bring a rejected or closed enquiry back to where it was. Only an owner or admin may, and a reason is needed.
     */
    public function reopen(Candidate $candidate, User $actor, string $reason): Candidate
    {
        if (! $candidate->stage->isClosed()) {
            throw new InvalidArgumentException('Only a rejected or closed enquiry can be reopened.');
        }

        if (! $actor->is_super_admin && ! in_array(ClubAccess::role($actor, $candidate->club), ['owner', 'admin'], true)) {
            throw new InvalidArgumentException('Only an owner or admin can reopen an enquiry.');
        }

        if (mb_strlen(trim($reason)) < 3) {
            throw new InvalidArgumentException('Add a short reason for reopening.');
        }

        $back = CandidateStage::tryFrom((string) $candidate->outcome_from_stage) ?? CandidateStage::Enquiry;
        $from = $candidate->stage;

        $candidate->update([
            'stage' => $back->isActive() ? $back : CandidateStage::Enquiry,
            'stage_entered_at' => now(),
            'outcome' => null,
            'outcome_reason' => null,
            'outcome_note' => null,
            'outcome_at' => null,
            'outcome_by' => null,
            'outcome_from_stage' => null,
        ]);

        $this->log($candidate, $actor, 'reopen', 'Reopened at '.$candidate->fresh()->stage->label(), $from, $candidate->stage, trim($reason));

        return $candidate->fresh();
    }

    /**
     * Record the result of the ballot in open lodge. Only the result is kept, never who voted or how. Being elected moves the
     * candidate to Accepted and starts the year allowed for initiation; not being elected rejects the candidate.
     */
    public function recordBallot(Candidate $candidate, ?User $actor, Carbon $date, string $result, ?string $note = null): Candidate
    {
        if ($candidate->stage !== CandidateStage::Proposed || $candidate->outcome !== null) {
            throw new InvalidArgumentException('A ballot can only be recorded for a candidate who has been proposed.');
        }

        if (! in_array($result, ['elected', 'not_elected'], true)) {
            throw new InvalidArgumentException('Choose whether the candidate was elected.');
        }

        if ($candidate->proposed_at && $date->lt($candidate->proposed_at)) {
            throw new InvalidArgumentException('The ballot cannot be before the date proposed ('.$candidate->proposed_at->format('j M Y').').');
        }

        return DB::transaction(function () use ($candidate, $actor, $date, $result, $note) {
            $candidate->update(['ballot_at' => $date, 'ballot_result' => $result]);

            if ($result === 'not_elected') {
                $this->log($candidate, $actor, 'ballot', 'Ballot on '.$date->format('j M Y').': not elected', null, null, trim((string) $note) ?: null);

                return $this->reject($candidate, $actor, 'failed_ballot', trim((string) $note) ?: 'Not elected at the ballot on '.$date->format('j M Y').'.');
            }

            $from = $candidate->stage;
            $candidate->update([
                'stage' => CandidateStage::Accepted,
                'stage_entered_at' => now(),
                'accepted_at' => now(),
                'initiate_by' => $date->copy()->addYear(),
            ]);

            $this->log($candidate, $actor, 'ballot', 'Ballot on '.$date->format('j M Y').': elected. To be initiated by '.$date->copy()->addYear()->format('j M Y').'.', $from, CandidateStage::Accepted, trim((string) $note) ?: null);

            return $candidate->fresh();
        });
    }

    /**
     * The committee's view, from its meeting or the vetting sign-off.
     */
    public function recordCommitteeDecision(Candidate $candidate, ?User $actor, bool $recommended, ?string $note = null): Candidate
    {
        $candidate->update($recommended ? ['committee_recommended_at' => now(), 'rule_159_cleared' => true] : ['committee_recommended_at' => null]);
        $this->log($candidate, $actor, 'committee', $recommended ? 'Committee recommends proceeding' : 'Committee deferred the decision', null, null, trim((string) $note) ?: null);

        return $candidate->fresh();
    }

    /**
     * Add a note, a call or a meeting to the history, dated for when it happened.
     */
    public function addNote(Candidate $candidate, ?User $actor, string $type, string $summary, ?string $body = null, ?Carbon $occurredAt = null): CandidateEvent
    {
        $summary = trim($summary);

        if (! in_array($type, CandidateEvent::NOTE_TYPES, true)) {
            throw new InvalidArgumentException('Choose a note, a call or a meeting.');
        }

        if ($summary === '') {
            throw new InvalidArgumentException('Say what happened.');
        }

        return $this->log($candidate, $actor, $type, $summary, null, null, trim((string) $body) ?: null, $occurredAt);
    }

    /**
     * Change a candidate's details and note which ones changed.
     *
     * @param  array<string, mixed>  $details
     */
    public function updateDetails(Candidate $candidate, array $details, ?User $actor = null): Candidate
    {
        $candidate->fill($details);
        $changed = array_keys($candidate->getDirty());
        $candidate->save();

        if ($changed !== []) {
            $this->log($candidate, $actor, 'edit', 'Details edited: '.implode(', ', array_map(fn ($field) => str_replace('_', ' ', $field), $changed)));
        }

        return $candidate->fresh();
    }

    /**
     * Convert an initiated candidate into a full active Member record.
     */
    public function convertCandidateToMember(Candidate $candidate, ?Carbon $initiationDate = null, ?User $actor = null, ?string $reason = null): Member
    {
        return DB::transaction(function () use ($candidate, $initiationDate, $actor, $reason) {
            $effectiveDate = $initiationDate ?? $candidate->initiation_date ?? Carbon::now();

            // Prevent duplicate conversion if member already exists
            if ($candidate->converted_member_id && $existingMember = Member::find($candidate->converted_member_id)) {
                return $existingMember;
            }

            $from = $candidate->stage;

            $member = Member::create([
                'club_id' => $candidate->club_id,
                'first_name' => $candidate->first_name,
                'last_name' => $candidate->last_name,
                'email' => $candidate->email,
                'phone' => $candidate->phone,
                'address_line_1' => $candidate->address,
                'postcode' => $candidate->postcode,
                'masonic_rank' => 'Bro',
                'membership_status' => MembershipStatus::Active,
                'current_office' => LodgeOffice::Member,
                'date_of_joining' => $effectiveDate,
                'date_of_initiation' => $effectiveDate,
            ]);

            $candidate->update([
                'stage' => CandidateStage::Initiated,
                'stage_entered_at' => now(),
                'initiation_date' => $effectiveDate,
                'converted_member_id' => $member->id,
                'on_hold_at' => null,
                'on_hold_until' => null,
            ]);

            $this->log($candidate, $actor, 'stage_change', 'Initiated on '.$effectiveDate->format('j M Y').' and added to the members list'.($reason ? ' (skipped ahead: '.$reason.')' : ''), $from, CandidateStage::Initiated);

            return $member;
        });
    }

    /**
     * Save the Form P details and the proposal on a candidate.
     *
     * @param  array<string, mixed>  $vettingData
     */
    public function updateFormPVetting(Candidate $candidate, array $vettingData, ?User $actor = null): Candidate
    {
        $candidate->update([
            'proposer_member_id' => $vettingData['proposer_member_id'] ?? $candidate->proposer_member_id,
            'seconder_member_id' => $vettingData['seconder_member_id'] ?? $candidate->seconder_member_id,
            'form_p_signed_at' => isset($vettingData['form_p_signed_at']) ? Carbon::parse($vettingData['form_p_signed_at']) : $candidate->form_p_signed_at,
            'belief_in_supreme_being' => $vettingData['belief_in_supreme_being'] ?? $candidate->belief_in_supreme_being,
            'no_criminal_record' => $vettingData['no_criminal_record'] ?? $candidate->no_criminal_record,
            'no_bankruptcies' => $vettingData['no_bankruptcies'] ?? $candidate->no_bankruptcies,
            'rule_159_cleared' => $vettingData['rule_159_cleared'] ?? $candidate->rule_159_cleared,
            'hermes_clearance_date' => isset($vettingData['hermes_clearance_date']) ? Carbon::parse($vettingData['hermes_clearance_date']) : $candidate->hermes_clearance_date,
            'proposed_at' => isset($vettingData['proposed_at']) ? Carbon::parse($vettingData['proposed_at']) : ($vettingData['proposed_at'] ?? $candidate->proposed_at),
        ]);

        $this->log($candidate, $actor, 'form_p', 'Form P and proposal details updated');

        return $candidate->fresh();
    }

    private function log(Candidate $candidate, ?User $actor, string $type, string $summary, ?CandidateStage $from = null, ?CandidateStage $to = null, ?string $body = null, ?Carbon $occurredAt = null): CandidateEvent
    {
        return CandidateEvent::create([
            'candidate_id' => $candidate->id,
            'club_id' => $candidate->club_id,
            'user_id' => $actor?->id,
            'type' => $type,
            'from_stage' => $from?->value,
            'to_stage' => $to?->value,
            'occurred_at' => $occurredAt ?? now(),
            'summary' => mb_substr($summary, 0, 255),
            'body' => $body,
        ]);
    }
}
