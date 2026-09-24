<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Someone's request to take over the listing of a lodge. A superadmin checks they belong to it
 * before they get access. Every status change is only made through LodgeClaimService, which also
 * writes the history.
 */
class LodgeClaim extends Model
{
    use HasFactory;

    public const PENDING = 'pending';

    public const MORE_INFO = 'more_info';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const WITHDRAWN = 'withdrawn';

    public const SUPERSEDED = 'superseded';

    public const OPEN = [self::PENDING, self::MORE_INFO];

    public const ROLES = [
        'master' => 'Worshipful Master (or the principal of the body)',
        'secretary' => 'Secretary',
        'treasurer' => 'Treasurer',
        'officer' => 'Another officer',
        'member' => 'A member',
    ];

    /**
     * The most claims one person may have waiting at once.
     */
    public const MAX_OPEN_PER_USER = 5;

    protected $fillable = ['lodge_id', 'user_id', 'status', 'claimant_role', 'message', 'phone', 'evidence'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Lodge, $this>
     */
    public function lodge(): BelongsTo
    {
        return $this->belongsTo(Lodge::class);
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

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return HasMany<LodgeClaimEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(LodgeClaimEvent::class)->orderBy('id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /**
     * @param  Builder<LodgeClaim>  $query
     * @return Builder<LodgeClaim>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN);
    }
}
