<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsletterType extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'is_external_subscribable',
        'require_approval',
        'is_mandatory',
        'require_home_club_info',
        'default_roles',
        'sender_name',
        'sender_email',
    ];

    protected function casts(): array
    {
        return [
            'is_external_subscribable' => 'boolean',
            'require_approval' => 'boolean',
            'is_mandatory' => 'boolean',
            'require_home_club_info' => 'boolean',
            'default_roles' => 'array',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(NewsletterSubscription::class);
    }

    public function newsletters(): HasMany
    {
        return $this->hasMany(Newsletter::class);
    }
}
