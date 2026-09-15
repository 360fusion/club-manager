<?php

namespace App\Domains\ClubAccounting\Models;

use App\Models\Club;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubNoticeOfMotion extends Model
{
    use HasFactory;

    protected $table = 'club_acc_notices_of_motion';

    protected $fillable = [
        'club_id',
        'committee_meeting_id',
        'agenda_item_id',
        'proposer_user_id',
        'proposer_name',
        'seconder_user_id',
        'seconder_name',
        'title',
        'motion_text',
        'rationale',
        'target_lodge_meeting_id',
        'status',
        'exported_to_summons_at',
        'ratified_at',
    ];

    protected function casts(): array
    {
        return [
            'exported_to_summons_at' => 'datetime',
            'ratified_at' => 'datetime',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(ClubCommitteeMeeting::class, 'committee_meeting_id');
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(ClubCommitteeAgendaItem::class, 'agenda_item_id');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposer_user_id');
    }

    public function seconder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seconder_user_id');
    }

    public function targetLodgeMeeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'target_lodge_meeting_id');
    }
}
