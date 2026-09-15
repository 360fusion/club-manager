<?php

namespace App\Domains\ClubAccounting\Models;

use App\Domains\ClubAccounting\Enums\CommitteeItemType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ClubCommitteeAgendaItem extends Model
{
    use HasFactory;

    protected $table = 'club_acc_committee_agenda_items';

    protected $fillable = [
        'committee_meeting_id',
        'order',
        'item_type',
        'title',
        'description',
        'discussion_notes',
        'recommendation_text',
        'is_approved',
        'reference_id',
        'reference_type',
    ];

    protected function casts(): array
    {
        return [
            'item_type' => CommitteeItemType::class,
            'is_approved' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(ClubCommitteeMeeting::class, 'committee_meeting_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ClubCommitteeTask::class, 'agenda_item_id');
    }

    public function noticesOfMotion(): HasMany
    {
        return $this->hasMany(ClubNoticeOfMotion::class, 'agenda_item_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
