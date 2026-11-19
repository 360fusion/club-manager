<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A visit a member made to another lodge, recorded by the member. Their own lodges' Secretaries see where and when
 * (not the note) on the Visiting page.
 */
class MemberVisit extends Model
{
    protected $fillable = ['user_id', 'lodge_id', 'lodge_name', 'visited_on', 'note'];

    protected function casts(): array
    {
        return ['visited_on' => 'date'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Lodge, $this> */
    public function lodge(): BelongsTo
    {
        return $this->belongsTo(Lodge::class);
    }
}
