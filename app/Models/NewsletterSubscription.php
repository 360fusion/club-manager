<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'newsletter_type_id',
        'user_id',
        'email',
        'name',
        'rank',
        'home_club_name',
        'home_club_number',
        'status', // active, pending_approval, unsubscribed, rejected
        'subscribed_at',
        'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function newsletterType(): BelongsTo
    {
        return $this->belongsTo(NewsletterType::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
