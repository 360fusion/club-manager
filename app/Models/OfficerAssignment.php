<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfficerAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'year',
        'meeting_id',
        'officer_role_id',
        'user_id',
        'custom_name',
        'prefix_titles',
        'suffix_titles',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function officerRole()
    {
        return $this->belongsTo(OfficerRole::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getFormattedNameAttribute()
    {
        $name = $this->user ? $this->user->name : $this->custom_name;
        $prefix = $this->prefix_titles ? $this->prefix_titles . ' ' : '';
        $suffix = $this->suffix_titles ? ', ' . $this->suffix_titles : '';
        return trim("{$prefix}{$name}{$suffix}");
    }
}
