<?php

namespace App\Models\Accounting;

use App\Contracts\Signable;
use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A charity's Independent Examiner's Report for one financial year: one named examiner,
 * one signature. examined_at stays null until that signature is completed.
 */
class IndependentExaminerReport extends Model implements Signable
{
    protected $fillable = [
        'club_id',
        'financial_year',
        'examiner_user_id',
        'examined_at',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'financial_year' => 'integer',
            'examined_at' => 'datetime',
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
    public function examiner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'examiner_user_id');
    }

    public function signatureLabel(string $purpose): string
    {
        return "Independent Examiner's Report — {$this->financial_year} accounts";
    }
}
