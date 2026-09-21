<?php

namespace App\Models\Accounting;

use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingContact extends Model
{
    use HasFactory;

    protected $table = 'accounting_contacts';

    protected $fillable = [
        'club_id',
        'user_id',
        'type',
        'name',
        'first_name',
        'middle_names',
        'last_name',
        'preferred_name',
        'contact_person',
        'email',
        'phone',
        'role',
        'tax_id',
        'address_line_1',
        'address_line_2',
        'city',
        'postcode',
        'country',
        'notes',
        'is_active',
        'is_member',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_member' => 'boolean',
    ];

    /**
     * Build the full display name from structured name parts.
     * Falls back to the legacy 'name' column if parts are not set.
     */
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->first_name,
            $this->middle_names,
            $this->last_name,
        ]);

        return implode(' ', $parts) ?: ($this->name ?? '');
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
