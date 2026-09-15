<?php

namespace App\Domains\ClubAccounting\Models;

use App\Domains\ClubAccounting\Enums\CommitteeMeetingStatus;
use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClubCommitteeMeeting extends Model
{
    use HasFactory;

    protected $table = 'club_acc_committee_meetings';

    protected $fillable = [
        'club_id',
        'title',
        'meeting_date',
        'location',
        'status',
        'notes_raw',
        'minutes_final',
        'chair_user_id',
        'secretary_user_id',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommitteeMeetingStatus::class,
            'meeting_date' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function chair(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chair_user_id');
    }

    public function secretary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretary_user_id');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(ClubCommitteeAttendee::class, 'committee_meeting_id');
    }

    public function agendaItems(): HasMany
    {
        return $this->hasMany(ClubCommitteeAgendaItem::class, 'committee_meeting_id')->orderBy('order');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ClubCommitteeTask::class, 'committee_meeting_id');
    }

    public function noticesOfMotion(): HasMany
    {
        return $this->hasMany(ClubNoticeOfMotion::class, 'committee_meeting_id');
    }
}
