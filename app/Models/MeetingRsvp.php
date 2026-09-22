<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingRsvp extends Model
{
    use HasFactory;

    protected $table = 'meeting_rsvps';

    protected $fillable = [
        'meeting_id',
        'user_id',
        'token_hash',
        'token_expires_at',
        'attendance_status',
        'apology_reason',
        'dietary_requirements',
        'payment_status',
        'payment_reference',
        'payment_method',
        'paid_at',
        'paid_recorded_by',
        'responded_at',
        'is_postal_printed',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'responded_at' => 'datetime',
        'is_postal_printed' => 'boolean',
    ];

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<MeetingRsvpGuest, $this>
     */
    /**
     * @return BelongsTo<User, $this>
     */
    public function paidRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_recorded_by');
    }

    public function guests(): HasMany
    {
        return $this->hasMany(MeetingRsvpGuest::class, 'meeting_rsvp_id');
    }
}
