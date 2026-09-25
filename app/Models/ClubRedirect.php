<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An address on a club's public site (after /site/{club}/) that sends visitors somewhere else.
 */
class ClubRedirect extends Model
{
    public const MAX_PER_CLUB = 200;

    protected $fillable = ['club_id', 'from_path', 'to_url', 'is_permanent'];

    protected function casts(): array
    {
        return ['is_permanent' => 'boolean'];
    }

    /**
     * The form a typed address is stored and matched in: no query or fragment, lower case, one leading
     * slash and no trailing one ("/Old Page/" becomes "/old page"), so a visitor's trailing slash or
     * capital letter still finds it.
     */
    public static function normalisePath(string $path): string
    {
        $path = strtolower(trim(explode('#', explode('?', trim($path), 2)[0], 2)[0]));

        return '/'.trim($path, '/');
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
