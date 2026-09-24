<?php

namespace App\Support;

use App\Models\Lodge;
use App\Models\LodgeSource;
use App\Models\MasonicHall;

/**
 * The pages a member can open to confirm a lodge's or hall's details, grouped by how much weight
 * they carry: UGLE first, then the lodge's own province, then third-party directories. Where UGLE
 * has no page for the exact place, its search for the postcode is offered and marked as a search,
 * so it is clear the member has to pick the result.
 *
 * @phpstan-type Link array{label: string, url: string, is_search: bool}
 * @phpstan-type Group array{tier: int, title: string, note: string, links: list<Link>}
 */
class LodgeReferenceLinks
{
    private const UGLE_SEARCH = 'https://www.ugle.org.uk/become-freemason/join-freemasonry/register-your-interest';

    private const TIER_TEXT = [
        1 => ['UGLE', 'The United Grand Lodge of England. This is the official source.'],
        2 => ["The lodge's province", "The province's own website. Provinces keep their lodges' details up to date."],
        3 => ['Other directories', 'Not official. Check them against the pages above.'],
    ];

    /**
     * @return list<Group>
     */
    public static function forLodge(Lodge $lodge): array
    {
        $links = [];

        foreach ($lodge->sources as $source) {
            $links[] = [LodgeSource::TIERS[$source->kind] ?? 3, ['label' => match ($source->kind) {
                LodgeSource::PROVINCE_LIST => "The province's list of lodges",
                LodgeSource::DIRECTORY_PAGE => 'OnTheSquare: this lodge',
                LodgeSource::CATALOGUE_PAGE => 'Worcestershire Masonic Library catalogue: units in this province',
                default => "This lodge on the province's website",
            }, 'url' => $source->url, 'is_search' => false]];
        }

        if ($lodge->sources->isEmpty() && $lodge->source_url) {
            $links[] = [2, ['label' => "This lodge on the province's website", 'url' => $lodge->source_url, 'is_search' => false]];
        }

        return self::group([...$links, ...self::ugleLinks($lodge->masonicHall)]);
    }

    /**
     * @return list<Group>
     */
    public static function forHall(MasonicHall $hall): array
    {
        $links = self::ugleLinks($hall);

        if ($hall->source_url && ! str_contains($hall->source_url, 'ugle.org.uk')) {
            $isWikipedia = str_contains((string) parse_url($hall->source_url, PHP_URL_HOST), 'wikipedia.org');
            $links[] = [$isWikipedia ? 3 : 2, ['label' => 'Where these details were taken from', 'url' => $hall->source_url, 'is_search' => false]];
        }

        return self::group($links);
    }

    /**
     * @return list<array{0: int, 1: Link}>
     */
    private static function ugleLinks(?MasonicHall $hall): array
    {
        if (! $hall) {
            return [];
        }

        $links = [];

        if ($hall->ugle_url) {
            $links[] = [1, ['label' => 'This meeting place on UGLE', 'url' => $hall->ugle_url, 'is_search' => false]];
        }

        if ($hall->postcode) {
            $links[] = [1, ['label' => 'Search UGLE for lodges near '.$hall->postcode, 'url' => self::ugleSearchUrl($hall->postcode), 'is_search' => true]];
        }

        return $links;
    }

    /**
     * @param  list<array{0: int, 1: Link}>  $links
     * @return list<Group>
     */
    private static function group(array $links): array
    {
        $groups = [];

        foreach (self::TIER_TEXT as $tier => [$title, $note]) {
            $inTier = array_values(array_map(fn (array $entry) => $entry[1], array_filter($links, fn (array $entry) => $entry[0] === $tier)));

            if ($inTier !== []) {
                $groups[] = ['tier' => $tier, 'title' => $title, 'note' => $note, 'links' => $inTier];
            }
        }

        return $groups;
    }

    public static function ugleSearchUrl(string $postcode): string
    {
        return self::UGLE_SEARCH.'?'.http_build_query([
            'proximity' => ['distance' => ['from' => 25], 'source_configuration' => ['origin_address' => $postcode]],
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
