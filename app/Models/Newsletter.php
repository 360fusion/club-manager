<?php

namespace App\Models;

use App\Casts\SanitizedHtml;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Newsletter extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'club_id',
        'newsletter_type_id',
        'subject',
        'content',
        'attachments',
        'target_roles',
        'status',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'content' => SanitizedHtml::class,
            'attachments' => 'array',
            'target_roles' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<NewsletterType, $this>
     */
    public function newsletterType(): BelongsTo
    {
        return $this->belongsTo(NewsletterType::class);
    }
}
