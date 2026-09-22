<?php

namespace App\Models\Accounting;

use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only audit trail for financial record changes. Rows are never edited or
 * deleted, same pattern as App\Models\EventPaymentLog: history has to be trustworthy
 * to be worth anything.
 */
class LedgerAuditLog extends Model
{
    use HasFactory;

    protected $table = 'accounting_ledger_audit_log';

    public const UPDATED_AT = null;

    protected $fillable = [
        'club_id',
        'entity_type',
        'entity_id',
        'action',
        'user_id',
        'summary',
        'before_json',
        'after_json',
    ];

    protected function casts(): array
    {
        return [
            'before_json' => 'array',
            'after_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        $guard = function () {
            throw new LogicException('The ledger audit trail cannot be changed or removed.');
        };

        static::updating($guard);
        static::deleting($guard);
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
