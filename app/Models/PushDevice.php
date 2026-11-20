<?php

namespace App\Models;

use Database\Factories\PushDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone the app is signed in on, with the token Apple (APNs) or Google (FCM) gave it for push, or a browser that turned on
 * notifications from the website (`web`: the token is a hash of its push address, `subscription` holds what sending needs).
 */
class PushDevice extends Model
{
    /** @use HasFactory<PushDeviceFactory> */
    use HasFactory;

    public const PLATFORMS = ['ios', 'android', 'web'];

    public const MAX_WEB_PER_USER = 10;

    /** The push services browsers use. Sending only ever goes to these, never to an address someone made up. */
    public const WEB_PUSH_HOSTS = '/^(fcm\.googleapis\.com|updates\.push\.services\.mozilla\.com|push\.services\.mozilla\.com|web\.push\.apple\.com|[a-z0-9-]+\.notify\.windows\.com)$/';

    protected $fillable = ['user_id', 'platform', 'token', 'subscription', 'last_seen_at'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime', 'subscription' => 'encrypted:array'];
    }

    public static function webToken(string $endpoint): string
    {
        return 'web:'.hash('sha256', $endpoint);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
