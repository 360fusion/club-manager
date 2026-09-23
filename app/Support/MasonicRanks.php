<?php

namespace App\Support;

use App\Models\Club;

/**
 * The Grand and Provincial rank lists a lodge chooses from.
 *
 * A list is an ordered array of `abbreviation` (what is stored on the member and shown in their name) and `title`
 * (the full rank, shown beside it in dropdowns). A lodge keeps its own copy in `settings.grand_ranks` and
 * `settings.provincial_ranks`, taken from its Grand Lodge's list when the lodge is created. A lodge with no copy
 * yet falls back to its Grand Lodge's list, then to the platform starter in config/masonic_ranks.php.
 */
final class MasonicRanks
{
    public const GRAND = 'grand';

    public const PROVINCIAL = 'provincial';

    public const KINDS = [self::GRAND, self::PROVINCIAL];

    public static function settingKey(string $kind): string
    {
        return $kind.'_ranks';
    }

    /**
     * The platform starter list.
     *
     * @return array<int, array{abbreviation: string, title: string}>
     */
    public static function starter(string $kind): array
    {
        return self::clean((array) config('masonic_ranks.'.$kind, []));
    }

    /**
     * Whether this club is a Masonic body, and so keeps rank lists.
     */
    public static function isMasonic(Club $club): bool
    {
        return in_array($club->clubType?->code, (array) config('masonic_ranks.club_type_codes', []), true);
    }

    /**
     * The list the club's Grand Lodge keeps, or null when it has none.
     *
     * @return array<int, array{abbreviation: string, title: string}>|null
     */
    public static function masterFor(Club $club, string $kind): ?array
    {
        $list = $club->province?->grandLodge?->{self::settingKey($kind)};

        return is_array($list) && $list !== [] ? self::clean($list) : null;
    }

    /**
     * The ranks a lodge may choose from.
     *
     * @return array<int, array{abbreviation: string, title: string}>
     */
    public static function forClub(Club $club, string $kind): array
    {
        $own = ($club->settings ?? [])[self::settingKey($kind)] ?? null;

        if (is_array($own)) {
            return self::clean($own);
        }

        return self::masterFor($club, $kind) ?? self::starter($kind);
    }

    /**
     * The choices for a dropdown. A value the member already holds that is not in the list is added at the end, so
     * editing an old record never loses a rank.
     *
     * @return array<int, array{value: string, label: string, in_list: bool}>
     */
    public static function optionsFor(Club $club, string $kind, ?string $current = null): array
    {
        $options = array_map(fn (array $rank) => [
            'value' => $rank['abbreviation'],
            'label' => $rank['title'] !== '' ? $rank['abbreviation'].' — '.$rank['title'] : $rank['abbreviation'],
            'in_list' => true,
        ], self::forClub($club, $kind));

        $current = trim((string) $current);

        if ($current !== '' && ! in_array($current, array_column($options, 'value'), true)) {
            $options[] = ['value' => $current, 'label' => $current.' (not in your list)', 'in_list' => false];
        }

        return $options;
    }

    /**
     * The values a member's rank may be: anything in the list, or what the member already holds.
     *
     * @return array<int, string>
     */
    public static function allowedValues(Club $club, string $kind, ?string $current = null): array
    {
        return array_column(self::optionsFor($club, $kind, $current), 'value');
    }

    /**
     * The list's own spelling of a rank written as an abbreviation or a full title, or null if it is not in the list.
     */
    public static function normalise(Club $club, string $kind, string $value): ?string
    {
        $key = self::key($value);

        if ($key === '') {
            return null;
        }

        foreach (self::forClub($club, $kind) as $rank) {
            if (self::key($rank['abbreviation']) === $key || self::key($rank['title']) === $key) {
                return $rank['abbreviation'];
            }
        }

        return null;
    }

    /**
     * Copy the lists into a new club's settings, unless it already has them. Masonic clubs only: the club's Grand
     * Lodge list if there is one, otherwise the starter for craft lodges and an empty list for other bodies.
     */
    public static function seedClub(Club $club): void
    {
        if (! self::isMasonic($club)) {
            return;
        }

        $settings = $club->settings ?? [];
        $starts = in_array($club->clubType?->code, (array) config('masonic_ranks.starter_club_type_codes', []), true);
        $changed = false;

        foreach (self::KINDS as $kind) {
            $key = self::settingKey($kind);

            if (! array_key_exists($key, $settings)) {
                $settings[$key] = self::masterFor($club, $kind) ?? ($starts ? self::starter($kind) : []);
                $changed = true;
            }
        }

        if ($changed) {
            $club->forceFill(['settings' => $settings])->saveQuietly();
        }
    }

    /**
     * Replace the club's lists with its Grand Lodge's (or the starter) again.
     */
    public static function resetClub(Club $club): void
    {
        $settings = $club->settings ?? [];
        $starts = ! self::isMasonic($club) || in_array($club->clubType?->code, (array) config('masonic_ranks.starter_club_type_codes', []), true);

        foreach (self::KINDS as $kind) {
            $settings[self::settingKey($kind)] = self::masterFor($club, $kind) ?? ($starts ? self::starter($kind) : []);
        }

        $club->forceFill(['settings' => $settings])->save();
    }

    /**
     * Tidy a stored list: trimmed entries in the right shape, without blanks or repeats, in their original order.
     *
     * @param  array<int|string, mixed>  $list
     * @return array<int, array{abbreviation: string, title: string}>
     */
    public static function clean(array $list): array
    {
        $seen = [];
        $clean = [];

        foreach ($list as $entry) {
            $abbreviation = trim((string) (is_array($entry) ? ($entry['abbreviation'] ?? '') : $entry));
            $title = is_array($entry) ? trim((string) ($entry['title'] ?? '')) : '';

            if ($abbreviation === '' || isset($seen[mb_strtolower($abbreviation)])) {
                continue;
            }

            $seen[mb_strtolower($abbreviation)] = true;
            $clean[] = ['abbreviation' => $abbreviation, 'title' => $title];
        }

        return $clean;
    }

    private static function key(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($value)) ?? '';
    }
}
