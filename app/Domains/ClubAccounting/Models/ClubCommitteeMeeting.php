<?php

namespace App\Domains\ClubAccounting\Models;

use App\Domains\ClubAccounting\Enums\CommitteeMeetingStatus;
use App\Models\Club;
use App\Models\Meeting;
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
        'linked_regular_meeting_id',
        'title',
        'meeting_date',
        'time_opened',
        'location',
        'status',
        'notes_raw',
        'draft_notes',
        'minutes_final',
        'chair_user_id',
        'secretary_user_id',
        'finalized_at',
    ];

    public function setDraftNotesAttribute($value): void
    {
        $this->attributes['notes_raw'] = $value;
    }

    public function getDraftNotesAttribute(): ?string
    {
        return $this->attributes['notes_raw'] ?? null;
    }

    protected function casts(): array
    {
        return [
            'status' => CommitteeMeetingStatus::class,
            'meeting_date' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function isPast(): bool
    {
        return $this->meeting_date && $this->meeting_date->isPast();
    }

    public function displayStatusLabel(): string
    {
        if ($this->isPast()) {
            return 'Past';
        }

        return match ($this->status) {
            CommitteeMeetingStatus::Draft, CommitteeMeetingStatus::DraftSaved => 'Draft',
            CommitteeMeetingStatus::Scheduled => 'Scheduled',
            default => $this->status->label(),
        };
    }

    public function displayBadgeClass(): string
    {
        if ($this->isPast()) {
            return 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-800';
        }

        return match ($this->status) {
            CommitteeMeetingStatus::Draft, CommitteeMeetingStatus::DraftSaved => 'bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-200 border-amber-200 dark:border-amber-800/60',
            CommitteeMeetingStatus::Scheduled => 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 border-blue-200 dark:border-blue-800/60',
            default => $this->status->badgeClass(),
        };
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function chair(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chair_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function secretary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretary_user_id');
    }

    /**
     * @return HasMany<ClubCommitteeAttendee, $this>
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(ClubCommitteeAttendee::class, 'committee_meeting_id');
    }

    /**
     * @return HasMany<ClubCommitteeAgendaItem, $this>
     */
    public function agendaItems(): HasMany
    {
        return $this->hasMany(ClubCommitteeAgendaItem::class, 'committee_meeting_id')->orderBy('order');
    }

    /**
     * @return HasMany<ClubCommitteeTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ClubCommitteeTask::class, 'committee_meeting_id');
    }

    /**
     * @return HasMany<ClubNoticeOfMotion, $this>
     */
    public function noticesOfMotion(): HasMany
    {
        return $this->hasMany(ClubNoticeOfMotion::class, 'committee_meeting_id');
    }

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function linkedRegularMeeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'linked_regular_meeting_id');
    }
}
