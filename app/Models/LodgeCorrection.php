<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone's report that a directory listing is wrong, with the values they think are right. A
 * superadmin applies it or turns it down, only through LodgeCorrectionService. Once applied,
 * `applied` keeps each changed field's old and new value, so a change can be traced and undone.
 */
class LodgeCorrection extends Model
{
    use HasFactory;

    public const PENDING = 'pending';

    public const APPLIED = 'applied';

    public const REJECTED = 'rejected';

    /**
     * What can be suggested, as shown on the form and in the queue. A hall that is not on our list
     * (`hall_text`) is only ever shown: a superadmin adds the hall first, then picks it.
     */
    public const FIELDS = [
        'meets_text' => 'When it meets',
        'masonic_hall_id' => 'Where it meets',
        'hall_text' => 'A hall not on our list',
        'status' => 'Whether it still meets',
        'name' => 'Name',
        'number' => 'Number',
        'website_url' => 'Website',
    ];

    /** The fields an empty box can remove, rather than being ignored. */
    public const CLEARABLE = ['number', 'website_url'];

    /** The statuses someone can report. */
    public const STATUSES = [
        'active' => 'Still meets',
        'dormant' => 'Has stopped meeting for now (dormant)',
        'erased' => 'Has closed, amalgamated or been erased',
    ];

    /**
     * The most suggestions one person may have waiting at once.
     */
    public const MAX_OPEN_PER_USER = 10;

    protected $fillable = ['lodge_id', 'user_id', 'changes', 'note'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'applied' => 'array',
            'decided_at' => 'datetime',
        ];
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

    public function isOpen(): bool
    {
        return $this->status === self::PENDING;
    }

    /**
     * @param  Builder<LodgeCorrection>  $query
     * @return Builder<LodgeCorrection>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::PENDING);
    }
}
