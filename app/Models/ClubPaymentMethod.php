<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One way a lodge can be paid, set up once and switched on per event.
 */
class ClubPaymentMethod extends Model
{
    public const BANK = 'bank_transfer';

    public const CARD = 'card_online';

    public const LATER = 'pay_later';

    public const DOOR = 'cash_on_door';

    public const TYPES = [self::BANK, self::CARD, self::LATER, self::DOOR];

    /** Types where the lodge may add a fee. Card and bank payments may only ever be discounted. */
    public const FEE_TYPES = [self::LATER, self::DOOR];

    protected $fillable = [
        'club_id',
        'type',
        'label',
        'instructions',
        'config',
        'default_adjustment_kind',
        'default_adjustment_mode',
        'default_adjustment_amount',
        'default_adjustment_scope',
        'due_days',
        'due_basis',
        'is_active',
        'sort_order',
    ];

    /** Bank details are shown to payers on purpose, but the Stripe keys in config must never leave the server. */
    protected $hidden = ['config'];

    protected function casts(): array
    {
        return [
            'config' => 'encrypted:array',
            'default_adjustment_amount' => 'decimal:2',
            'due_days' => 'integer',
            'is_active' => 'boolean',
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
     * @return HasMany<EventRegistration, $this>
     */
    public function paymentRegistrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'payment_method_id');
    }

    public function allowsFee(): bool
    {
        return in_array($this->type, self::FEE_TYPES, true);
    }

    /**
     * The bank details a payer needs, and nothing else from config.
     *
     * @return array{account_name: ?string, sort_code: ?string, account_number: ?string, reference_prefix: ?string}
     */
    public function bankDetails(): array
    {
        $config = $this->config ?? [];

        return [
            'account_name' => $config['account_name'] ?? null,
            'sort_code' => $config['sort_code'] ?? null,
            'account_number' => $config['account_number'] ?? null,
            'reference_prefix' => $config['reference_prefix'] ?? null,
        ];
    }

    public function hasStripeKeys(): bool
    {
        return ! empty($this->config['stripe_secret_key']);
    }

    /**
     * A card option can only be offered once it can both take a payment and hear back that it was made.
     */
    public function isReadyForCards(): bool
    {
        return $this->type === self::CARD && $this->hasStripeKeys() && ! empty($this->config['stripe_webhook_secret']);
    }
}
