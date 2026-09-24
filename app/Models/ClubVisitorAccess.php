<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A visiting brother's request to see a lodge's summonses, and the lodge's answer. Only an
 * approved request lets someone outside the lodge see them, and only when the lodge has chosen
 * to share with approved visitors.
 */
class ClubVisitorAccess extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    public const REVOKED = 'revoked';

    protected $table = 'club_visitor_access';

    protected $fillable = ['club_id', 'user_id', 'home_lodge_name', 'home_lodge_number', 'rank', 'message'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
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
