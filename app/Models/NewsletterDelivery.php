<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One newsletter to one person: whether it went, and the private token their unsubscribe and "view in browser"
 * links use. One row per newsletter and email address, so sending it again can never double-send.
 */
class NewsletterDelivery extends Model
{
    public const QUEUED = 'queued';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    protected $fillable = ['newsletter_id', 'club_id', 'email', 'name', 'user_id', 'subscription_id', 'token', 'status', 'error', 'sent_at'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Newsletter, $this>
     */
    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }
}
