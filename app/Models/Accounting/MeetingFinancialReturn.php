<?php

namespace App\Models\Accounting;

use App\Models\Club;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingFinancialReturn extends Model
{
    use HasFactory;

    protected $table = 'meeting_financial_returns';

    protected $fillable = [
        'club_id',
        'meeting_id',
        'return_date',
        'dining_fee_per_head',
        'paid_diners_count',
        'waived_diners_count',
        'waived_reason',
        'kitchen_cost_per_head',
        'kitchen_vendor_name',
        'raffle_amount',
        'alms_amount',
        'donations_amount',
        'bequest_amount',
        'total_dining_revenue',
        'total_kitchen_bill',
        'net_dining_surplus',
        'total_charity_collected',
        'net_bank_deposit',
        'vendor_bill_id',
        'journal_entry_id',
        'is_draft',
        'notes',
    ];

    protected $casts = [
        'return_date' => 'date',
        'is_draft' => 'boolean',
        'dining_fee_per_head' => 'decimal:2',
        'kitchen_cost_per_head' => 'decimal:2',
        'raffle_amount' => 'decimal:2',
        'alms_amount' => 'decimal:2',
        'donations_amount' => 'decimal:2',
        'bequest_amount' => 'decimal:2',
        'total_dining_revenue' => 'decimal:2',
        'total_kitchen_bill' => 'decimal:2',
        'net_dining_surplus' => 'decimal:2',
        'total_charity_collected' => 'decimal:2',
        'net_bank_deposit' => 'decimal:2',
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
     * @return BelongsTo<Bill, $this>
     */
    public function vendorBill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'vendor_bill_id');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
