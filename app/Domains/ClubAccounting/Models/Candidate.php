<?php

namespace App\Domains\ClubAccounting\Models;

use App\Domains\ClubAccounting\Enums\CandidateStage;
use App\Models\Club;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Candidate extends Model
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

    public function isFormPComplete(): bool
    {
        return $this->form_p_signed_at !== null
            && $this->belief_in_supreme_being
            && $this->no_criminal_record
            && $this->no_bankruptcies
            && $this->proposer_member_id !== null
            && $this->seconder_member_id !== null;
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
