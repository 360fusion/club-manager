<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One phone's Face ID or fingerprint sign-in (DeviceSignInController). The key itself lives only on the phone.
 */
class DeviceSignInKey extends Model
{
    public const PLATFORMS = ['ios', 'android'];

    /** Keys per person; the oldest goes when another phone is added. */
    public const MAX_PER_USER = 10;

    /** A key not used for this long stops working. */
    public const UNUSED_DAYS = 180;

    protected $fillable = ['user_id', 'key_hash', 'platform', 'name', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public static function hashOf(string $key): string
    {
        return hash('sha256', $key);
    }

    public function isStale(): bool
    {
        return ($this->last_used_at ?? $this->created_at)->lt(now()->subDays(self::UNUSED_DAYS));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
