<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The only code that calls OpenStreetMap's Nominatim search, used by the Map block's address search.
 * Nominatim's usage policy asks for an identifying User-Agent, at most one request a second, no
 * type-ahead and cached results, so every answer is kept for 30 days, a search only happens when an admin
 * presses the button, and follow-up attempts are spaced out.
 *
 * Nominatim often finds nothing (or the wrong end of the street) for "Name of hall, Street, Town POSTCODE", so a
 * UK postcode is searched first, then the text as typed, then the text without the building's name. A result that
 * was not found from the text as typed is flagged approximate.
 */
class Geocoder
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    private const UK_POSTCODE = '/\b([A-Z]{1,2}[0-9][A-Z0-9]?)\s*([0-9][A-Z]{2})\b/i';

    private ?float $lastRequestAt = null;

    /**
     * @return array{results: list<array{lat: float, lng: float, name: string}>, approximate: bool, matched: string|null}|null null when the service could not be reached and nothing was found
     */
    public function search(string $query, int $limit = 5): ?array
    {
        $query = $this->normalise($query);

        if ($query === '') {
            return ['results' => [], 'approximate' => false, 'matched' => null];
        }

        $failed = false;

        foreach ($this->variants($query) as $variant) {
            $results = $this->fetch($variant, $limit);

            if ($results === null) {
                $failed = true;

                continue;
            }

            if ($results !== []) {
                return ['results' => $results, 'approximate' => $variant !== $query, 'matched' => $variant];
            }
        }

        return $failed ? null : ['results' => [], 'approximate' => false, 'matched' => null];
    }

    /**
     * The searches to try in turn. A UK postcode pins a building to within a few metres, so when there is one
     * it goes first; then what was typed, then the address without its first part (usually the building's name).
     *
     * @return list<string>
     */
    private function variants(string $query): array
    {
        $variants = [];

        if (preg_match(self::UK_POSTCODE, $query, $match) === 1) {
            $variants[] = strtoupper($match[1].' '.$match[2]);
        }

        $variants[] = $query;

        $parts = array_values(array_filter(array_map('trim', explode(',', $query))));

        if (count($parts) > 1) {
            $variants[] = implode(', ', array_slice($parts, 1));
        }

        return array_values(array_unique($variants));
    }

    private function normalise(string $query): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $query));
    }

    /**
     * @return list<array{lat: float, lng: float, name: string}>|null null when the request failed
     */
    private function fetch(string $query, int $limit): ?array
    {
        $key = 'geocode:v2:'.$limit.':'.md5(mb_strtolower($query));

        if (($cached = Cache::get($key)) !== null) {
            return $cached;
        }

        $this->keepToOneRequestASecond();

        try {
            $response = Http::withUserAgent((string) config('services.nominatim.user_agent'))
                ->timeout(10)
                ->acceptJson()
                ->get(self::ENDPOINT, ['q' => $query, 'format' => 'jsonv2', 'limit' => $limit]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $results = collect($response->json() ?? [])
            ->filter(fn ($row) => is_array($row) && isset($row['lat'], $row['lon']))
            ->map(fn (array $row) => ['lat' => round((float) $row['lat'], 6), 'lng' => round((float) $row['lon'], 6), 'name' => (string) ($row['display_name'] ?? '')])
            ->values()
            ->all();

        Cache::put($key, $results, now()->addDays(30));

        return $results;
    }

    private function keepToOneRequestASecond(): void
    {
        $gap = ((int) config('services.nominatim.gap_ms', 1100)) / 1000;

        if ($this->lastRequestAt !== null && $gap > 0) {
            $wait = $gap - (microtime(true) - $this->lastRequestAt);

            if ($wait > 0) {
                usleep((int) ($wait * 1_000_000));
            }
        }

        $this->lastRequestAt = microtime(true);
    }
}
