<?php

namespace App\Models\Accounting;

use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringBillTemplate extends Model
{
    use HasFactory;

    protected $table = 'accounting_recurring_bill_templates';

    protected $fillable = [
        'club_id',
        'vendor_name',
        'category',
        'amount',
        'frequency',
        'next_run_date',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'next_run_date' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
