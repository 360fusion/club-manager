<?php

namespace App\Models;

use App\Enums\SignatureRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A request for a named person to sign a document (Form P, an auditor sign-off, ...), and once actioned, the
 * signature itself: how it was given, by whom, from where, and when. The drawn image, if any, is kept in the
 * 'signature' media collection on the private disk.
 */
class SignatureRequest extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'club_id',
        'signable_type',
        'signable_id',
        'purpose',
        'signer_type',
        'signer_id',
        'signer_name',
        'signer_email',
        'token_hash',
        'status',
        'requested_by_user_id',
        'requested_at',
        'expires_at',
        'method',
        'typed_name',
        'consent_ip',
        'consent_user_agent',
        'signed_at',
        'document_hash',
        'notes',
    ];

    protected $hidden = ['token_hash'];

    /** The emailed link's plaintext token, only set on the request that (re)issued it. */
    public ?string $plainToken = null;

    protected function casts(): array
    {
        return [
            'status' => SignatureRequestStatus::class,
            'requested_at' => 'datetime',
            'expires_at' => 'datetime',
            'signed_at' => 'datetime',
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
     * @return MorphTo<Model, $this>
     */
    public function signable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function signer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
