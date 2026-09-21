<?php

namespace App\Models;

use App\Support\Currencies;
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

    public const PAYPAL = 'paypal';

    public const TYPES = [self::BANK, self::CARD, self::PAYPAL, self::LATER, self::DOOR];

    /** Types paid on a provider's own page. They need the lodge's credentials and can only be discounted. */
    public const ONLINE_TYPES = [self::CARD, self::PAYPAL];

    /** The currencies PayPal can take that the app supports. */
    public const PAYPAL_CURRENCIES = ['GBP', 'EUR', 'USD', 'AUD', 'NZD', 'CAD', 'CHF', 'BRL'];

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
     * @return array{account_name: ?string, bank_name: ?string, sort_code: ?string, account_number: ?string, iban: ?string, reference_prefix: ?string, code_label: string}
     */
    public function bankDetails(): array
    {
        $config = $this->config ?? [];

        return [
            'account_name' => $config['account_name'] ?? null,
            'bank_name' => $config['bank_name'] ?? null,
            'sort_code' => $config['sort_code'] ?? null,
            'account_number' => $config['account_number'] ?? null,
            'iban' => $config['iban'] ?? null,
            'reference_prefix' => $config['reference_prefix'] ?? null,
            'code_label' => Currencies::bankCodeLabel($this->club?->currencyCode()),
        ];
    }

    /**
     * A bank option can only be offered once payers have somewhere to send the money.
     */
    public function hasBankDetails(): bool
    {
        $config = $this->config ?? [];

        return $this->type === self::BANK && (! empty($config['account_number']) || ! empty($config['iban']));
    }

    /**
     * Whether everything this kind of option needs has been filled in, so it can be switched on. Pay later and
     * pay on the night need nothing more.
     */
    public function hasCompleteDetails(): bool
    {
        return match ($this->type) {
            self::BANK => $this->hasBankDetails(),
            self::CARD => $this->isReadyForCards(),
            self::PAYPAL => $this->isReadyForPayPal(),
            default => true,
        };
    }

    /**
     * What is missing, in words, for the message shown when someone tries to switch an unfinished option on.
     */
    public function missingDetailsMessage(): string
    {
        return match ($this->type) {
            self::BANK => 'Add the bank account number or IBAN first, so people know where to pay.',
            self::CARD => $this->usesConnect() ? 'Connect Stripe and accept the terms first.' : 'Add the Stripe keys and webhook secret first.',
            self::PAYPAL => 'Add the PayPal client ID, secret and webhook ID first.',
            default => '',
        };
    }

    /**
     * Put this option (enabled) on every upcoming event that has no row for it yet, so a lodge never has to switch it
     * on event by event. An event that already has a row, on or off, keeps its own choice.
     *
     * @return int how many events it was added to
     */
    public function offerOnUpcomingEvents(): int
    {
        $events = Event::where('club_id', $this->club_id)
            ->where('starts_at', '>=', now())
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->whereDoesntHave('paymentMethods', fn ($q) => $q->where('payment_method_id', $this->id))
            ->get(['id']);

        foreach ($events as $event) {
            EventPaymentMethod::create(['event_id' => $event->id, 'payment_method_id' => $this->id, 'is_enabled' => true]);
        }

        return $events->count();
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
        if ($this->type !== self::CARD) {
            return false;
        }

        if ($this->usesConnect()) {
            return (bool) config('platform_payments.enabled') && ! empty(config('platform_payments.stripe_secret')) && $this->club?->platformAccount?->canTakePayments() === true;
        }

        return $this->hasStripeKeys() && ! empty($this->config['stripe_webhook_secret']);
    }

    /**
     * Whether card payments go through the lodge's Stripe account connected to the platform, rather than keys the lodge pasted in.
     */
    public function usesConnect(): bool
    {
        return $this->type === self::CARD && ($this->config['stripe_mode'] ?? 'keys') === 'connect';
    }

    /**
     * A PayPal option needs the lodge's app credentials and its webhook id, so payments are confirmed even if the payer never returns.
     */
    public function isReadyForPayPal(): bool
    {
        $config = $this->config ?? [];

        return $this->type === self::PAYPAL && ! empty($config['paypal_client_id']) && ! empty($config['paypal_client_secret']) && ! empty($config['paypal_webhook_id']);
    }

    public function isOnline(): bool
    {
        return in_array($this->type, self::ONLINE_TYPES, true);
    }

    /**
     * Whether this option can be offered to payers in the given currency: always for offline types, once set up for online ones.
     */
    public function isReadyFor(string $currency): bool
    {
        return match ($this->type) {
            self::CARD => $this->isReadyForCards(),
            self::PAYPAL => $this->isReadyForPayPal() && in_array(strtoupper($currency), self::PAYPAL_CURRENCIES, true),
            self::BANK => $this->hasBankDetails(),
            default => true,
        };
    }
}
