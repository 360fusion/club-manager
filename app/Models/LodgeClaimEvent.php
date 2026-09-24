<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One line in a claim's history. Written once and never changed, so an approval can always be
 * traced back to who did what and when.
 */
class LodgeClaimEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['lodge_claim_id', 'actor_id', 'type', 'note'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('A claim event cannot be changed.'));
        static::deleting(fn () => throw new LogicException('A claim event cannot be deleted.'));
    }

    /**
     * @return BelongsTo<LodgeClaim, $this>
     */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(LodgeClaim::class, 'lodge_claim_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
