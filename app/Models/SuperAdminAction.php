<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * What a platform superadmin did: one row per request that changed something under /superadmin, with the values
 * submitted and each record's before and after. Append-only, like LedgerAuditLog and EventPaymentLog.
 */
class SuperAdminAction extends Model
{
    protected $table = 'superadmin_actions';

    public const UPDATED_AT = null;

    protected $fillable = ['actor_id', 'action', 'method', 'path', 'status', 'route_parameters', 'input', 'changes', 'ip'];

    protected function casts(): array
    {
        return [
            'route_parameters' => 'array',
            'input' => 'array',
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        $guard = function () {
            throw new LogicException('The superadmin audit log cannot be changed or removed.');
        };

        static::updating($guard);
        static::deleting($guard);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
