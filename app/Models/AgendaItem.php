<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    public function presenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presenter_user_id');
    }
}
