<?php

namespace Launchpad\Billing;

/** One switch for VAT: Stripe Tax works the tax out and collects the address and tax number it needs. */
final class Vat
{
    public static function enabled(): bool
    {
        return (bool) config('launchpad.billing.automatic_tax');
    }

    /** The checkout options VAT needs, or nothing when it is off */
    public static function checkoutOptions(): array
    {
        return self::enabled() ? [
            'automatic_tax' => ['enabled' => true],
            'billing_address_collection' => 'required',
            'customer_update' => ['address' => 'auto', 'name' => 'auto'],
            'tax_id_collection' => ['enabled' => true],
        ] : [];
    }
}
