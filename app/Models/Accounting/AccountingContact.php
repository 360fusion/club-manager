<?php

namespace App\Models\Accounting;

use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingContact extends Model
{
    use HasFactory;

    protected $table = 'accounting_contacts';

    protected $fillable = [
        'club_id',
        'type',
        'name',
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
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
