<?php

namespace App\Models;

use App\Observers\DonationContributionTeamEvents;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(DonationContributionTeamEvents::class)]
class DonationContribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'donation_id',
        'donor_name',
        'donor_email',
        'amount',
        'payment_status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Donation, $this>
     */
    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }
}
