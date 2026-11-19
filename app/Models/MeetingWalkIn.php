<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A visitor signed in at the door of a meeting without having replied (a brother from another lodge, as written in the
 * Tyler's book). Members and their guests are signed in on their replies instead.
 */
class MeetingWalkIn extends Model
{
    protected $fillable = ['meeting_id', 'name', 'rank', 'lodge', 'signed_in_by'];

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
