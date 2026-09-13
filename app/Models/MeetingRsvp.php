<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'responded_at',
        'is_postal_printed',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'responded_at' => 'datetime',
        'is_postal_printed' => 'boolean',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function guests()
    {
        return $this->hasMany(MeetingRsvpGuest::class, 'meeting_rsvp_id');
    }
}
