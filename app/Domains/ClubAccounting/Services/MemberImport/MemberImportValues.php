<?php

namespace App\Domains\ClubAccounting\Services\MemberImport;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use DateTimeImmutable;

/**
 * Turns the text in a spreadsheet cell into what the roster stores: cleaned text, real dates, a status, an office.
 */
final class MemberImportValues
{
    public const DATE_ORDERS = ['dmy' => 'Day / month / year (UK)', 'mdy' => 'Month / day / year (US)', 'ymd' => 'Year / month / day'];

    /** @var array<int, string> Leading words in a "Name" cell that are a title, not a first name. */
    private const NAME_TITLES = ['bro', 'wbro', 'vwbro', 'rwbro', 'mwbro', 'w.bro', 'v.w.bro', 'r.w.bro', 'mr', 'mrs', 'ms', 'miss', 'dr', 'rev', 'revd', 'sir', 'prof'];

    /** @var array<string, string> Spellings of a masonic rank (letters and digits only) => stored rank. */
    private const RANK_ALIASES = [
        'bro' => 'Bro', 'brother' => 'Bro', 'mastermason' => 'Bro', 'mm' => 'Bro', 'ea' => 'Bro', 'fc' => 'Bro',
        'wbro' => 'WBro', 'wbrother' => 'WBro', 'worshipfulbro' => 'WBro', 'worshipfulbrother' => 'WBro', 'pm' => 'WBro',
        'vwbro' => 'VWBro', 'vwbrother' => 'VWBro', 'veryworshipful' => 'VWBro', 'veryworshipfulbrother' => 'VWBro',
        'rwbro' => 'RWBro', 'rwbrother' => 'RWBro', 'rightworshipful' => 'RWBro', 'rightworshipfulbrother' => 'RWBro',
        'mwbro' => 'MWBro', 'mwbrother' => 'MWBro', 'mostworshipful' => 'MWBro', 'mostworshipfulbrother' => 'MWBro',
    ];

    /** @var array<string, string> Spellings of an office besides its own value, label and short code. */
    private const OFFICE_ALIASES = [
        'master' => 'wm', 'worshipfulmaster' => 'wm', 'wmaster' => 'wm',
        'immediatepastmaster' => 'ipm', 'pastmaster' => 'ipm',
        'seniorwarden' => 'sw', 'juniorwarden' => 'jw',
        'chap' => 'chaplain', 'treas' => 'treasurer', 'sec' => 'secretary',
        'directorofceremonies' => 'dc', 'cs' => 'charity_steward',
        'seniordeacon' => 'sd', 'juniordeacon' => 'jd',
        'adc' => 'assistant_dc', 'assistantdc' => 'assistant_dc', 'assistantdirectorofceremonies' => 'assistant_dc',
        'org' => 'organist', 'asstsec' => 'assistant_sec', 'assistantsecretary' => 'assistant_sec',
        'ig' => 'inner_guard', 'innerguard' => 'inner_guard',
        'brother' => 'member', 'bro' => 'member', 'brethren' => 'member', 'memberbrethren' => 'member', 'none' => 'member',
    ];

    /** @var array<string, string> Spellings of a status besides its own value and label. */
    private const STATUS_ALIASES = [
        'current' => 'active', 'activemember' => 'active', 'hon' => 'honorary', 'honorarymember' => 'honorary',
        'resign' => 'resigned', 'dead' => 'deceased', 'died' => 'deceased', 'excluded' => 'excluded_rule_181',
        'historicalmember' => 'historical', 'past' => 'historical',
    ];

    /**
     * Trim, drop control characters, and remove the apostrophe an export puts before a cell that starts with a
     * formula character, so a file we exported reads back the way it was.
     */
    public static function clean(?string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $value) ?? '';
        $value = trim($value);

        if (strlen($value) > 1 && $value[0] === "'" && str_contains('=+-@', $value[1])) {
            $value = substr($value, 1);
        }

        return $value;
    }

    /**
     * A date as Y-m-d, or null when it cannot be read. ISO dates and spreadsheet serial numbers are always accepted.
     */
    public static function date(string $value, string $order = 'dmy'): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (ctype_digit($value) && (int) $value >= 20000 && (int) $value <= 80000) {
            return (new DateTimeImmutable('1899-12-30'))->modify('+'.(int) $value.' days')->format('Y-m-d');
        }

        $formats = match ($order) {
            'mdy' => ['m/d/Y', 'm-d-Y', 'm.d.Y', 'm/d/y'],
            'ymd' => ['Y/m/d', 'Y.m.d'],
            default => ['d/m/Y', 'd-m-Y', 'd.m.Y', 'd/m/y'],
        };

        $formats = array_merge(['Y-m-d'], $formats, ['j M Y', 'j F Y', 'd M y', 'j-M-Y']);

        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            if ($date && (! $errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                $year = (int) $date->format('Y');

                return $year >= 1800 && $year <= 2200 ? $date->format('Y-m-d') : null;
            }
        }

        return null;
    }

    /**
     * Which day/month order the dates in these cells use: month first only when a second part is above 12 and
     * no first part is, otherwise the UK order.
     *
     * @param  array<int, string>  $samples
     */
    public static function detectDateOrder(array $samples): string
    {
        $dayFirst = false;
        $monthFirst = false;

        foreach ($samples as $sample) {
            if (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-]\d{2,4}$/', trim($sample), $m)) {
                $dayFirst = $dayFirst || (int) $m[1] > 12;
                $monthFirst = $monthFirst || (int) $m[2] > 12;
            }
        }

        return $monthFirst && ! $dayFirst ? 'mdy' : 'dmy';
    }

    /**
     * The stored masonic rank (Bro, WBro, VWBro, RWBro or MWBro) for however it was written, or null when it is
     * not a rank. A member's title comes from this rank, so it must be one of these.
     */
    public static function masonicRank(string $value): ?string
    {
        return self::RANK_ALIASES[MemberImportFields::normalise($value)] ?? null;
    }

    public static function office(string $value): ?LodgeOffice
    {
        $key = MemberImportFields::normalise($value);

        if ($key === '') {
            return null;
        }

        foreach (LodgeOffice::cases() as $office) {
            $candidates = [$office->value, $office->label(), $office->shortCode(), $office->name];

            if (in_array($key, array_map([MemberImportFields::class, 'normalise'], $candidates), true)) {
                return $office;
            }
        }

        $alias = self::OFFICE_ALIASES[$key] ?? null;

        return $alias ? LodgeOffice::from($alias) : null;
    }

    public static function status(string $value): ?MembershipStatus
    {
        $key = MemberImportFields::normalise($value);

        foreach (MembershipStatus::cases() as $status) {
            $candidates = [$status->value, $status->label(), $status->name];

            if (in_array($key, array_map([MemberImportFields::class, 'normalise'], $candidates), true)) {
                return $status;
            }
        }

        $alias = self::STATUS_ALIASES[$key] ?? null;

        return $alias ? MembershipStatus::from($alias) : null;
    }

    /**
     * "WBro John Arthur Smith" => rank WBro, first John, middle Arthur, last Smith. A leading masonic title sets
     * the rank; other titles (Mr, Dr) are dropped, because a member's title comes from their rank.
     *
     * @return array{masonic_rank?: string, first_name: string, middle_names: ?string, last_name: string}
     */
    public static function splitName(string $full): array
    {
        $parts = preg_split('/\s+/', trim($full), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $rank = [];

        if (count($parts) > 2 && in_array(mb_strtolower(rtrim($parts[0], '.')), self::NAME_TITLES, true)) {
            $found = self::masonicRank($parts[0]);
            array_shift($parts);

            if ($found !== null) {
                $rank = ['masonic_rank' => $found];
            }
        }

        if (count($parts) === 1) {
            return $rank + ['first_name' => $parts[0], 'middle_names' => null, 'last_name' => ''];
        }

        $last = array_pop($parts);
        $first = array_shift($parts) ?? '';

        return $rank + ['first_name' => $first, 'middle_names' => $parts ? implode(' ', $parts) : null, 'last_name' => $last];
    }

    /**
     * Lower case, without titles, dots or extra spaces, for comparing two people's names.
     */
    public static function nameKey(?string $first, ?string $last): string
    {
        $key = fn (?string $part) => trim(preg_replace('/[^a-z ]/', '', mb_strtolower((string) $part)) ?? '');

        return $key($first).'|'.$key($last);
    }
}
