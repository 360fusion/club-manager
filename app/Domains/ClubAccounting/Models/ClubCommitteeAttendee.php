<?php

namespace App\Domains\ClubAccounting\Models;

use App\Domains\ClubAccounting\Enums\AttendanceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubCommitteeAttendee extends Model
{
    use HasFactory;

    protected $table = 'club_acc_committee_attendees';

    protected $fillable = [
        'committee_meeting_id',
        'user_id',
        'name',
        'role_title',
        'attendance_type',
        'notes',
        'pack_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'attendance_type' => AttendanceType::class,
            'pack_sent_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(ClubCommitteeMeeting::class, 'committee_meeting_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
