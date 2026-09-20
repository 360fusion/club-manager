<?php

namespace App\Domains\ClubAccounting\Models;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Models\Accounting\Account;
use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'club_acc_bank_accounts';

    protected $fillable = [
        'club_id',
        'account_id',
        'bank_name',
        'account_name',
        'account_type',
        'account_number',
        'sort_code',
        'currency',
        'opening_balance',
        'is_active',
        'paypal_client_id',
        'paypal_client_secret',
        'paypal_environment',
        'paypal_merchant_id',
        'paypal_connected_at',
        'stripe_secret_key',
        'stripe_publishable_key',
        'stripe_connected_at',
        'sumup_api_key',
        'sumup_merchant_code',
        'sumup_connected_at',
        'gocardless_access_token',
        'gocardless_environment',
        'gocardless_webhook_secret',
        'gocardless_connected_at',
        'last_synced_at',
        'sync_status',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
            'paypal_client_secret' => 'encrypted',
            'paypal_connected_at' => 'datetime',
            'stripe_secret_key' => 'encrypted',
            'stripe_connected_at' => 'datetime',
            'sumup_api_key' => 'encrypted',
            'sumup_connected_at' => 'datetime',
            'gocardless_access_token' => 'encrypted',
            'gocardless_webhook_secret' => 'encrypted',
            'gocardless_connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function imports(): HasMany
    {
        return $this->hasMany(BankImport::class, 'bank_account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class, 'bank_account_id');
    }

    public function getUnreconciledCountAttribute(): int
    {
        return $this->transactions()
            ->where('status', BankTransactionStatus::Unmatched->value)
            ->count();
    }

    public function getStatementBalanceAttribute(): float
    {
        $lastTx = $this->transactions()
            ->whereNotNull('balance_after')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastTx) {
            return (float) $lastTx->balance_after;
        }

        $sumTx = (float) $this->transactions()->sum('amount');

        return (float) $this->opening_balance + $sumTx;
    }

    public function getLedgerBalanceAttribute(): float
    {
        if ($this->account) {
            return (float) $this->account->balance;
        }

        return $this->statement_balance;
    }

    public function getFormattedAccountTypeAttribute(): string
    {
        return match ($this->account_type) {
            'current' => 'High Street Current Account',
            'savings' => 'Savings Account',
            'credit_card' => 'Credit Card',
            'payment_gateway' => 'Payment Gateway / Online Processor',
            'merchant' => 'Card Reader & Merchant Account',
            'cash' => 'Cash Drawer / Petty Cash',
            default => ucfirst(str_replace('_', ' ', $this->account_type)),
        };
    }
}
