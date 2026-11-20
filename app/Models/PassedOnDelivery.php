<?php

namespace App\Models;

use App\Domains\ClubAccounting\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's copy of a passed-on item: a user, or a roster member with no account (`member_id`, by email only).
 * `kind` and `via_club_id` say why they got it (which list of which of their lodges); `follower` is someone who
 * follows the lodge. The private `token` opens it, and changes their choices, without signing in.
 */
class PassedOnDelivery extends Model
{
    protected $fillable = [
        'item_id', 'user_id', 'member_id', 'email', 'kind', 'via_club_id', 'lodge_id', 'dedupe_key', 'bell', 'emailed',
        'follower', 'token', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'bell' => 'boolean',
            'emailed' => 'boolean',
            'follower' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PassedOnItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(PassedOnItem::class, 'item_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Club, $this> */
    public function viaClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'via_club_id');
    }
}
