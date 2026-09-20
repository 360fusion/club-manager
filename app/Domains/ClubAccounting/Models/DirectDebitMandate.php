<?php

namespace App\Domains\ClubAccounting\Models;

use App\Models\Club;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectDebitMandate extends Model
{
    use HasFactory;

    protected $table = 'club_acc_direct_debit_mandates';

    protected $fillable = [
        'club_id',
        'user_id',
        'membership_id',
        'gocardless_customer_id',
        'gocardless_mandate_id',
        'scheme',
        'status',
        'bank_name',
        'account_holder_name',
        'account_number_ending',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }
}
