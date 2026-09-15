<?php

namespace App\Domains\ClubAccounting\Services;

use App\Domains\ClubAccounting\Enums\CandidateStage;
use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Models\Candidate;
use App\Domains\ClubAccounting\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CandidateTransitionService
{
    /**
     * Transition candidate to a new stage with statutory validations.
     */
    public function transitionStage(Candidate $candidate, CandidateStage|string $newStage, array $attributes = []): Candidate
    {
        $targetStage = $newStage instanceof CandidateStage ? $newStage : CandidateStage::from($newStage);

        return DB::transaction(function () use ($candidate, $targetStage, $attributes) {
            // Apply any provided attributes (e.g. proposer, seconder, notes, statutory declarations)
            if (!empty($attributes)) {
                $candidate->fill($attributes);
            }

            // Stage specific rules
            if ($targetStage === CandidateStage::LodgeCommittee) {
                // Statutory requirement for committee vetting: Proposer & Seconder required
                if (!$candidate->proposer_member_id || !$candidate->seconder_member_id) {
                    throw new InvalidArgumentException("Candidate requires both a proposer and seconder before moving to Lodge Committee.");
                }
            }

            if ($targetStage === CandidateStage::Initiated) {
                $initDate = isset($attributes['initiation_date'])
                    ? Carbon::parse($attributes['initiation_date'])
                    : ($candidate->initiation_date ?? Carbon::now());

                $candidate->stage = CandidateStage::Initiated;
                $candidate->initiation_date = $initDate;
                $candidate->save();

                $this->convertCandidateToMember($candidate, $initDate);
                return $candidate->fresh();
            }

            $candidate->stage = $targetStage;
            $candidate->save();

            return $candidate->fresh();
        });
    }

    /**
     * Convert an initiated candidate into a full active Member record.
     */
    public function convertCandidateToMember(Candidate $candidate, ?Carbon $initiationDate = null): Member
    {
        return DB::transaction(function () use ($candidate, $initiationDate) {
            $effectiveDate = $initiationDate ?? $candidate->initiation_date ?? Carbon::now();

            // Prevent duplicate conversion if member already exists
            if ($candidate->converted_member_id && $existingMember = Member::find($candidate->converted_member_id)) {
                return $existingMember;
            }

            $member = Member::create([
                'club_id' => $candidate->club_id,
                'title' => 'Bro',
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
                'initiation_date' => $effectiveDate,
                'converted_member_id' => $member->id,
            ]);

            return $member;
        });
    }

    /**
     * Save statutory Form P vetting details on a candidate.
     */
    public function updateFormPVetting(Candidate $candidate, array $vettingData): Candidate
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
        ]);

        return $candidate->fresh();
    }
}
