<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecurringRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'name',
        'occurrence',
        'day_of_week',
        'active_months',
        'default_start_time',
        'default_rehearsal_time',
        'default_venue',
        'default_dress_code',
    ];

    protected $casts = [
        'active_months' => 'array',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function meetings()
    {
        return $this->hasMany(Meeting::class);
    }
}
