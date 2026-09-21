<?php

namespace App\Models;

use Database\Factories\ClubEmailTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One lodge's own wording for an email, used instead of the platform default until the lodge resets it.
 */
class ClubEmailTemplate extends Model
{
    /** @use HasFactory<ClubEmailTemplateFactory> */
    use HasFactory;

    protected $fillable = ['club_id', 'template_key', 'subject', 'body_html'];

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
