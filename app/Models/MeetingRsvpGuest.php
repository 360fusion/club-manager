<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingRsvpGuest extends Model
{
    use HasFactory;

    protected $table = 'meeting_rsvp_guests';

    protected $fillable = [
        'meeting_rsvp_id',
        'guest_name',
        'guest_title_rank',
        'home_club_lodge',
        'attending_dining',
        'dietary_requirements',
        'dining_fee',
        'payment_status',
    ];

    protected $casts = [
        'attending_dining' => 'boolean',
        'dining_fee' => 'decimal:2',
    ];

    public function rsvp(): BelongsTo
    {
        return $this->belongsTo(MeetingRsvp::class, 'meeting_rsvp_id');
    }
}
