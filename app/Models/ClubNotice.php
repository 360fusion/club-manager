<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A short notice sent to a group of a lodge's members (everyone, the diners or non-repliers of the next meeting, or
 * the officers), to their phones and by email. Sent by the queued SendClubNotice job, which fills in the counts.
 */
class ClubNotice extends Model
{
    public const AUDIENCES = ['all', 'diners', 'not_replied', 'officers'];

    protected $fillable = [
        'club_id',
        'sent_by',
        'audience',
        'meeting_id',
        'title',
        'body',
        'push',
        'email',
        'recipient_count',
        'push_count',
        'email_count',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'push' => 'boolean',
            'email' => 'boolean',
            'sent_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
