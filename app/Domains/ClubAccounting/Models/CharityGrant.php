<?php

namespace App\Domains\ClubAccounting\Models;

use App\Domains\ClubAccounting\Enums\GrantApprovalStatus;
use App\Models\Club;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CharityGrant extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'club_acc_charity_grants';

    protected $fillable = [
        'club_id',
        'recipient_name',
        'purpose',
        'amount',
        'relief_chest_number',
        'approval_status',
        'bacs_reference',
        'proposer_member_id',
        'seconder_member_id',
        'committee_meeting_id',
        'meeting_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approval_status' => GrantApprovalStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Club, $this>
     */
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
     * @return BelongsTo<ClubCommitteeMeeting, $this>
     */
    public function committeeMeeting(): BelongsTo
    {
        return $this->belongsTo(ClubCommitteeMeeting::class, 'committee_meeting_id');
    }

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }
}
