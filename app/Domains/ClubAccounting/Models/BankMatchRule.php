<?php

namespace App\Domains\ClubAccounting\Models;

use App\Models\Club;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A match the treasurer has made before, remembered by the (normalised) bank description so
 * the same recurring payee is suggested straight away next time.
 */
class BankMatchRule extends Model
{
    protected $table = 'club_acc_bank_match_rules';

    protected $fillable = [
        'club_id',
        'description_pattern',
        'match_type',
        'nominal_code',
        'hit_count',
        'created_from_transaction_id',
    ];

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
