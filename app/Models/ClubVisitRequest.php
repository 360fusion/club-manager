<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A visiting brother asking to attend one of a lodge's meetings, and the lodge's answer. Unlike ClubVisitorAccess it
 * shares nothing: it only tells the secretary who wants to come, and when.
 */
class ClubVisitRequest extends Model
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    protected $fillable = ['club_id', 'user_id', 'meeting_date', 'home_lodge_name', 'home_lodge_number', 'rank', 'dining', 'message'];

    protected function casts(): array
    {
        return ['meeting_date' => 'date', 'dining' => 'boolean', 'decided_at' => 'datetime'];
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
