<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FraternalVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'visit_type',
        'club_name',
        'delegation_leader_name',
        'guest_count',
        'notes',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }
}
