<?php

namespace App\Domains\ClubAccounting\Models;

use App\Domains\ClubAccounting\Enums\TaskStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubCommitteeTask extends Model
{
    use HasFactory;

    protected $table = 'club_acc_committee_tasks';

    protected $fillable = [
        'committee_meeting_id',
        'agenda_item_id',
        'assigned_to_user_id',
        'assigned_to_name',
        'title',
        'description',
        'due_date',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'due_date' => 'date:Y-m-d',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ClubCommitteeMeeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(ClubCommitteeMeeting::class, 'committee_meeting_id');
    }

    /**
     * @return BelongsTo<ClubCommitteeAgendaItem, $this>
     */
    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(ClubCommitteeAgendaItem::class, 'agenda_item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
