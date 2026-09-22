<?php

namespace App\Domains\ClubAccounting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One thing that happened to a candidate: a note, a call, a meeting, a stage change, a rejection. The history is
 * never rewritten: only plain notes can be changed or removed, everything else stays as it was recorded.
 */
class CandidateEvent extends Model
{
    public const NOTE_TYPES = ['note', 'call', 'meeting'];

    protected $table = 'club_acc_candidate_events';

    protected $fillable = ['candidate_id', 'club_id', 'user_id', 'type', 'from_stage', 'to_stage', 'occurred_at', 'summary', 'body'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        $guard = function (self $event) {
            if (! in_array($event->getOriginal('type') ?? $event->type, self::NOTE_TYPES, true)) {
                throw new LogicException('A candidate\'s history cannot be changed or removed.');
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    /**
     * @return BelongsTo<Candidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
