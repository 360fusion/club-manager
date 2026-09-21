<?php

namespace App\Support;

use App\Models\Club;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The currencies a lodge can keep its books in.
 *
 * Only currencies for countries where grand lodges (and so lodges) exist are
 * listed. To support a lodge in a new country, add its currency and countries
 * here; it then becomes selectable as soon as a grand lodge or province there
 * is added, or a club is created that uses it.
 *
 * Each lodge keeps all of its books in one currency of its own choosing. Amounts
 * are stored as plain numbers, so a lodge's currency is fixed once it has
 * financial records (see Club::currencyIsLocked()).
 */
final class Currencies
{
    public const DEFAULT = 'GBP';

    /**
     * @var array<string, array{name: string, symbol: string, countries: list<string>}>
     */
    private const REGISTRY = [
        'GBP' => ['name' => 'Pound sterling', 'symbol' => '£', 'countries' => ['England', 'Scotland', 'Wales', 'Northern Ireland', 'United Kingdom', 'Isle of Man', 'Channel Islands', 'Jersey', 'Guernsey']],
        'EUR' => ['name' => 'Euro', 'symbol' => '€', 'countries' => ['Ireland', 'Malta', 'France', 'Germany', 'Spain']],
        'USD' => ['name' => 'US dollar', 'symbol' => '$', 'countries' => ['United States']],
        'AUD' => ['name' => 'Australian dollar', 'symbol' => '$', 'countries' => ['Australia']],
        'NZD' => ['name' => 'New Zealand dollar', 'symbol' => '$', 'countries' => ['New Zealand']],
        'CAD' => ['name' => 'Canadian dollar', 'symbol' => '$', 'countries' => ['Canada']],
        'ZAR' => ['name' => 'South African rand', 'symbol' => 'R', 'countries' => ['South Africa']],
        'INR' => ['name' => 'Indian rupee', 'symbol' => '₹', 'countries' => ['India']],
        'CHF' => ['name' => 'Swiss franc', 'symbol' => 'CHF ', 'countries' => ['Switzerland']],
        'BRL' => ['name' => 'Brazilian real', 'symbol' => 'R$', 'countries' => ['Brazil']],
    ];

    /**
     * Every currency the system knows, whether or not a lodge uses it yet.
     *
     * @return list<string>
     */
    public static function known(): array
    {
        return array_keys(self::REGISTRY);
    }

    public static function isKnown(?string $code): bool
    {
        return $code !== null && isset(self::REGISTRY[strtoupper($code)]);
    }

    /**
     * Currencies that lodges actually use: those of countries with a grand
     * lodge or province, plus any a club has already chosen, and the default.
     *
     * @return array<string, array{code: string, name: string, symbol: string}>
     */
    public static function available(): array
    {
        $codes = [self::DEFAULT => true];

        if (Schema::hasTable('grand_lodges')) {
            $countries = DB::table('grand_lodges')->whereNotNull('country')->pluck('country')
                ->merge(Schema::hasTable('provinces') ? DB::table('provinces')->whereNotNull('country')->pluck('country') : collect())
                ->unique();

            foreach ($countries as $country) {
                if ($code = self::forCountry((string) $country)) {
                    $codes[$code] = true;
                }
            }
        }

        if (Schema::hasTable('clubs')) {
            foreach (DB::table('clubs')->whereNotNull('settings')->pluck('settings') as $settings) {
                $code = strtoupper((string) (json_decode($settings, true)['currency'] ?? ''));

                if (self::isKnown($code)) {
                    $codes[$code] = true;
                }
            }
        }

        $available = [];

        foreach (array_keys(self::REGISTRY) as $code) {
            if (isset($codes[$code])) {
                $available[$code] = self::describe($code);
            }
        }

        return $available;
    }

    /**
     * @return array{code: string, name: string, symbol: string}
     */
    public static function describe(string $code): array
    {
        $code = self::isKnown($code) ? strtoupper($code) : self::DEFAULT;

        return ['code' => $code, 'name' => self::REGISTRY[$code]['name'], 'symbol' => self::REGISTRY[$code]['symbol']];
    }

    public static function forCountry(?string $country): ?string
    {
        if ($country === null || $country === '') {
            return null;
        }

        foreach (self::REGISTRY as $code => $info) {
            foreach ($info['countries'] as $name) {
                if (strcasecmp($name, $country) === 0) {
                    return $code;
                }
            }
        }

        return null;
    }

    /**
     * What the bank's routing code is called where the currency is used, for the bank details a lodge gives payers.
     */
    public static function bankCodeLabel(?string $code): string
    {
        return match (strtoupper((string) $code)) {
            'AUD' => 'BSB',
            'USD' => 'Routing number',
            'CAD' => 'Transit and institution number',
            'ZAR' => 'Branch code',
            'INR' => 'IFSC code',
            'EUR', 'CHF' => 'BIC / SWIFT',
            'BRL' => 'Bank and branch code',
            default => 'Sort code',
        };
    }

    public static function symbol(?string $code): string
    {
        return self::describe($code ?? self::DEFAULT)['symbol'];
    }

    /**
     * Symbol for a club's own currency; falls back to the default outside a club.
     */
    public static function symbolFor(?Club $club): string
    {
        return self::symbol($club?->currencyCode());
    }

    /**
     * Currency code for a club known only by id (services that were not handed the Club).
     */
    public static function codeForClubId(?int $clubId): string
    {
        $club = $clubId ? Club::with('province.grandLodge')->find($clubId) : null;

        return $club?->currencyCode() ?? self::DEFAULT;
    }

    /**
     * A formatted amount in the club's currency, e.g. "€1,250.00".
     */
    public static function format(float|int|string|null $amount, ?Club $club = null, int $decimals = 2): string
    {
        $value = (float) $amount;
        $sign = $value < 0 ? '-' : '';

        return $sign.self::symbolFor($club).number_format(abs($value), $decimals);
    }
}
