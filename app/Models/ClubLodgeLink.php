<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A lodge on one of a club's lists: a sister lodge (the hall's lodges are on it by default; `excluded` is one the
 * Secretary unticked) or a reciprocal visit lodge. Read the lists through LodgeLinks.
 */
class ClubLodgeLink extends Model
{
    public const SISTER = 'sister';

    public const RECIPROCAL = 'reciprocal';

    public const RELATIONS = [self::SISTER, self::RECIPROCAL];

    protected $fillable = ['club_id', 'lodge_id', 'relation', 'excluded'];

    protected function casts(): array
    {
        return ['excluded' => 'boolean'];
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** @return BelongsTo<Lodge, $this> */
    public function lodge(): BelongsTo
    {
        return $this->belongsTo(Lodge::class);
    }
}
