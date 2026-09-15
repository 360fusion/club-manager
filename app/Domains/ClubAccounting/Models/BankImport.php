<?php

namespace App\Domains\ClubAccounting\Models;

use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankImport extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'club_acc_bank_imports';

    protected $fillable = [
        'club_id',
        'filename',
        'account_number',
        'sort_code',
        'imported_by_member_id',
        'total_lines',
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'total_lines' => 'integer',
            'total_amount' => 'decimal:2',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'imported_by_member_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class, 'bank_import_id');
    }
}
