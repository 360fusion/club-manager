<?php

namespace App\Domains\ClubAccounting\Models;

use App\Contracts\Signable;
use App\Domains\ClubAccounting\Enums\CandidateStage;
use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Candidate extends Model implements Signable
{
    use HasFactory, SoftDeletes;

    protected $table = 'club_acc_candidates';

    protected $fillable = [
        'club_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'occupation',
        'address',
        'postcode',
        'stage',
        'proposer_member_id',
        'seconder_member_id',
        'form_p_signed_at',
        'rule_159_cleared',
        'hermes_clearance_date',
        'belief_in_supreme_being',
        'no_criminal_record',
        'no_bankruptcies',
        'interview_notes',
        'notes',
        'initiation_date',
        'converted_member_id',
        'candidate_type',
        'grand_lodge_number',
        'mother_lodge_info',
        'source',
        'source_note',
        'owner_user_id',
        'stage_entered_at',
        'on_hold_at',
        'on_hold_until',
        'outcome',
        'outcome_reason',
        'outcome_note',
        'outcome_at',
        'outcome_by',
        'outcome_from_stage',
        'committee_recommended_at',
        'proposed_at',
        'ballot_at',
        'ballot_result',
        'accepted_at',
        'initiate_by',
    ];

    protected $casts = [
        'stage' => CandidateStage::class,
        'date_of_birth' => 'date',
        'form_p_signed_at' => 'datetime',
        'rule_159_cleared' => 'boolean',
        'hermes_clearance_date' => 'date',
        'belief_in_supreme_being' => 'boolean',
        'no_criminal_record' => 'boolean',
        'no_bankruptcies' => 'boolean',
        'interview_notes' => 'array',
        'initiation_date' => 'date',
        'stage_entered_at' => 'datetime',
        'on_hold_at' => 'datetime',
        'on_hold_until' => 'date',
        'outcome_at' => 'datetime',
        'committee_recommended_at' => 'datetime',
        'proposed_at' => 'date',
        'ballot_at' => 'date',
        'accepted_at' => 'datetime',
        'initiate_by' => 'date',
    ];

    /* =========================================================================
     * ACCESSORS & MUTATORS
     * ========================================================================= */

    public function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => trim("{$this->first_name} {$this->last_name}")
        );
    }

    /**
     * Form P is signed and there is a proposer and a different seconder. (A candidate's declarations are dealt with
     * by the Province and the Grand Secretary, not ticked off here.)
     */
    public function isFormPComplete(): bool
    {
        return $this->form_p_signed_at !== null
            && $this->proposer_member_id !== null
            && $this->seconder_member_id !== null
            && $this->proposer_member_id !== $this->seconder_member_id;
    }

    public function signatureLabel(string $purpose): string
    {
        $name = trim("{$this->first_name} {$this->last_name}");

        return match ($purpose) {
            'form_p_proposer' => "Form P — proposing {$name} for membership",
            'form_p_seconder' => "Form P — seconding {$name} for membership",
            default => "Form P for {$name}",
        };
    }

    /**
     * Still moving through the process: not initiated, rejected or closed.
     */
    public function isActive(): bool
    {
        return $this->stage->isActive() && $this->outcome === null;
    }

    public function isOnHold(): bool
    {
        return $this->on_hold_at !== null;
    }

    public function daysInStage(): int
    {
        return (int) floor(($this->stage_entered_at ?? $this->updated_at ?? now())->diffInDays(now(), true));
    }

    /* =========================================================================
     * RELATIONSHIPS
     * ========================================================================= */

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function proposer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'proposer_member_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function seconder(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'seconder_member_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    /**
     * @return HasMany<CandidateEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(CandidateEvent::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function convertedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'converted_member_id');
    }

    /* =========================================================================
     * SCOPES
     * ========================================================================= */

    public function scopeStage(Builder $query, CandidateStage|string $stage): Builder
    {
        $stageVal = $stage instanceof CandidateStage ? $stage->value : $stage;

        return $query->where('stage', $stageVal);
    }

    public function scopePendingCommittee(Builder $query): Builder
    {
        return $query->where('stage', CandidateStage::LodgeCommittee->value);
    }

    public function scopeInitiated(Builder $query): Builder
    {
        return $query->where('stage', CandidateStage::Initiated->value);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = '%'.trim($term).'%';

        return $query->where(function (Builder $q) use ($term) {
            $q->where('first_name', 'like', $term)
                ->orWhere('last_name', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('occupation', 'like', $term);
        });
    }
}
