<?php

namespace Launchpad\Billing;

use Illuminate\Http\Request;

/** A promo code a visitor arrived with (`/pricing?promo=SPRING`): kept in the session through signing up, until the checkout uses it */
final class PromoCode
{
    private const KEY = 'launchpad.promo';

    /** Stripe's own rule for a code: letters, digits and dashes */
    public static function remember(Request $request): void
    {
        $code = $request->query('promo');

        if (is_string($code) && preg_match('/^[A-Za-z0-9-]{3,40}$/', $code)) {
            $request->session()->put(self::KEY, $code);
        }
    }

    public static function pending(): ?string
    {
        return session(self::KEY);
    }

    public static function forget(): void
    {
        session()->forget(self::KEY);
    }

    /** "20% off for 3 months", "£5.00 off once": what a discount is, in words */
    public static function describe(?float $percentOff, ?int $amountOff, string $duration, ?int $months = null): string
    {
        $value = $percentOff !== null ? rtrim(rtrim(number_format($percentOff, 2, '.', ''), '0'), '.').'%' : Money::format((int) $amountOff);

        return match ($duration) {
            'repeating' => trans_choice('{1} :value off for :count month|[2,*] :value off for :count months', (int) $months, ['value' => $value]),
            'forever' => __(':value off for as long as they subscribe', ['value' => $value]),
            default => __(':value off on the first payment', ['value' => $value]),
        };
    }
}
