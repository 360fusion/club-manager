<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LodgeFollow extends Model
{
    protected $fillable = ['user_id', 'lodge_id', 'in_calendar'];

    protected function casts(): array
    {
        return ['in_calendar' => 'boolean'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Lodge, $this>
     */
    public function lodge(): BelongsTo
    {
        return $this->belongsTo(Lodge::class);
    }
}
