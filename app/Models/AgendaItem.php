<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgendaItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'item_number',
        'title',
        'description',
        'is_ballot',
        'is_installation',
        'presenter_user_id',
    ];

    protected $casts = [
        'is_ballot' => 'boolean',
        'is_installation' => 'boolean',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function presenter()
    {
        return $this->belongsTo(User::class, 'presenter_user_id');
    }
}
