<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * @return BelongsTo<OfficerRole, $this>
     */
    public function officerRole(): BelongsTo
    {
        return $this->belongsTo(OfficerRole::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFormattedNameAttribute(): string
    {
        $name = $this->user ? $this->user->name : $this->custom_name;
        $prefix = $this->prefix_titles ? $this->prefix_titles.' ' : '';
        $suffix = $this->suffix_titles ? ', '.$this->suffix_titles : '';

        return trim("{$prefix}{$name}{$suffix}");
    }
}
