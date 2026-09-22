<?php

namespace App\Models\Accounting;

use App\Contracts\Signable;
use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records that two elected auditors (never the Treasurer or Secretary — Book of
 * Constitutions Rule 153) have verified a financial year's statement of accounts.
 */
class AccountingYearAudit extends Model implements Signable
{
    use HasFactory;

    protected $table = 'accounting_year_audits';

    protected $fillable = [
        'club_id',
        'financial_year',
        'auditor_one_user_id',
        'auditor_two_user_id',
        'signed_off_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'financial_year' => 'integer',
            'signed_off_at' => 'datetime',
        ];
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
    public function auditorOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_one_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function auditorTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_two_user_id');
    }

    public function signatureLabel(string $purpose): string
    {
        return "Auditor sign-off — {$this->financial_year} statement of accounts";
    }
}
