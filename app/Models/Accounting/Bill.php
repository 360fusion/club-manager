<?php

namespace App\Models\Accounting;

use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bill extends Model
{
    use HasFactory;

    protected $table = 'accounting_bills';

    protected $fillable = [
        'club_id',
        'bill_number',
        'vendor_name',
        'category',
        'amount',
        'due_date',
        'status',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date:Y-m-d',
            'paid_at' => 'datetime',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
