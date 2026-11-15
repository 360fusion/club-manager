<?php

namespace Database\Seeders;

use App\Models\ClubType;
use App\Models\Lodge;
use App\Models\LodgeSchedule;
use App\Models\LodgeSource;
use App\Models\MasonicHall;
use App\Models\Province;
use App\Support\MeetingScheduleParser;
use App\Support\Months;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LodgeSeeder extends Seeder
{
    /**
     * The columns a lodge CSV may carry. `order` is a `club_types.code`, `province_code` a
     * `provinces.code` and `hall_postcode` finds the hall the lodge meets in, or `hall_town` when only the town is known
     * (used only when the province has exactly one masonic hall in that town). `meets_text` is the
     * wording from the source, kept as written; the regular pattern is worked out from it.
     * `source_url` is the page the details were read from (a province page, or the directory's page where nothing better exists) `directory_url` is an extra link to the lodge's page on OnTheSquare and `catalogue_url` one to the Worcestershire Masonic Library catalogue's list for its province. `installation_text` is a month, or the words for one; without it the month is read from
     * `meets_text` where the wording names one.
     */
    public const COLUMNS = [
        'province_code', 'order', 'number', 'name', 'hall_postcode', 'hall_town', 'meets_text',
        'installation_text', 'website_url', 'source_url', 'directory_url', 'catalogue_url',
    ];

    /**
     * Load every CSV in database/data/lodges (one per province). Lodges are matched on their slug,
     * so re-running refreshes them from the files without creating duplicates. Claiming, website
     * and description are never touched, and a schedule a superadmin entered by hand is kept.
     */
    public function run(): void
    {
        // Everything already stored is read once and the changes are written in bulk: one query per lodge row at most,
        // instead of a dozen (the files hold thousands of lodges).
        DB::transaction(function () {
            $types = ClubType::pluck('id', 'code');
            $provinces = Province::pluck('id', 'code');
            $halls = MasonicHall::whereNotNull('postcode')->get(['id', 'postcode'])
                ->mapWithKeys(fn (MasonicHall $hall) => [self::normalisePostcode($hall->postcode) => $hall->id]);
            $hallsByTown = MasonicHall::where('kind', 'hall')->whereNotNull('town')->whereNotNull('province_id')->get(['id', 'province_id', 'town'])
                ->groupBy(fn (MasonicHall $hall) => $hall->province_id.'|'.strtolower(trim($hall->town)));
            $hallUgleUrls = MasonicHall::whereNotNull('ugle_url')->pluck('ugle_url', 'id');
            $parser = new MeetingScheduleParser;
            $skipped = 0;
            $rows = $this->rows();
            $lodgesPerUrl = array_count_values(array_filter(array_column($rows, 'source_url')));

            $lodges = Lodge::get()->keyBy('slug');
            $sources = LodgeSource::get(['id', 'lodge_id', 'kind', 'url'])->groupBy('lodge_id');
            // A lodge with a schedule a superadmin entered by hand keeps it, and gets no imported one.
            $handEntered = LodgeSchedule::where('source', '!=', 'import')->distinct()->pluck('lodge_id')->flip();
            $seeded = [];
            $schedules = [];
            $newSources = [];
            $oldSources = [];
            $now = now();

            foreach ($rows as $row) {
                if (! $types->has($row['order'])) {
                    $skipped++;

                    continue;
                }

                $provinceId = $provinces[$row['province_code']] ?? null;
                $sameTown = $provinceId && $row['hall_town'] ? $hallsByTown->get($provinceId.'|'.strtolower(trim($row['hall_town']))) : null;
                $hallId = $halls[self::normalisePostcode((string) $row['hall_postcode'])] ?? ($sameTown?->count() === 1 ? $sameTown->first()->id : null);

                $installation = Months::number($row['installation_text']) ?? ($row['meets_text'] ? $parser->installationMonth($row['meets_text']) : null);

                $lodge = $lodges[$row['slug']] ?? new Lodge(['slug' => $row['slug']]);
                $lodge->fill([
                    'club_type_id' => $types[$row['order']],
                    'province_id' => $provinces[$row['province_code']] ?? $lodge->province_id,
                    'masonic_hall_id' => $hallId ?? $lodge->masonic_hall_id,
                    'name' => $row['name'],
                    'number' => $row['number'],
                    'meets_text' => $row['meets_text'],
                    'installation_month' => $installation ?? $lodge->installation_month,
                    'source_url' => $row['source_url'],
                ]);
                $lodge->save();
                $seeded[] = $lodge->id;

                // The province link is either the lodge's own page or a list page, never both.
                $thirdParty = $this->thirdPartyKind($row['source_url']);
                $provinceKind = $thirdParty ? LodgeSource::PROVINCE_PAGE : $this->listingKind($row['source_url'], $lodgesPerUrl);
                $wanted = [
                    LodgeSource::PROVINCE_PAGE => null,
                    LodgeSource::PROVINCE_LIST => null,
                    LodgeSource::DIRECTORY_PAGE => $thirdParty === LodgeSource::DIRECTORY_PAGE ? $row['source_url'] : $row['directory_url'],
                    LodgeSource::CATALOGUE_PAGE => $thirdParty === LodgeSource::CATALOGUE_PAGE ? $row['source_url'] : $row['catalogue_url'],
                    LodgeSource::UGLE_HALL => $hallUgleUrls[$lodge->masonic_hall_id] ?? null,
                ];
                $wanted[$provinceKind] = $thirdParty ? null : $row['source_url'];
                $this->syncSources($lodge, $wanted, $sources->get($lodge->id, collect())->keyBy('kind'), $newSources, $oldSources, $now);

                $patterns = $row['meets_text'] ? $parser->parseAll($row['meets_text']) : [];

                if ($patterns !== [] && ! $handEntered->has($lodge->id)) {
                    // The installation is a meeting too. With one pattern its month joins that pattern; with
                    // several, it is left alone unless one of them already covers it.
                    if ($installation && count($patterns) === 1 && ! in_array($installation, $patterns[0]['months'], true)) {
                        $patterns[0]['months'] = [...$patterns[0]['months'], $installation];
                        sort($patterns[0]['months']);
                    }

                    foreach ($patterns as $pattern) {
                        $schedules[] = [
                            'lodge_id' => $lodge->id, 'masonic_hall_id' => $lodge->masonic_hall_id, 'source' => 'import',
                            'kind' => $pattern['kind'] ?? 'regular', 'occurrence' => $pattern['occurrence'], 'day_of_week' => $pattern['day_of_week'],
                            'months' => json_encode($pattern['months']), 'start_time' => $pattern['start_time'] ?? null,
                            'created_at' => $now, 'updated_at' => $now,
                        ];
                    }
                }
            }

            foreach (array_chunk($oldSources, 500) as $ids) {
                LodgeSource::whereIn('id', $ids)->delete();
            }
            foreach (array_chunk($newSources, 500) as $chunk) {
                LodgeSource::insert($chunk);
            }
            foreach (array_chunk($seeded, 500) as $ids) {
                LodgeSchedule::whereIn('lodge_id', $ids)->where('source', 'import')->delete();
            }
            foreach (array_chunk($schedules, 500) as $chunk) {
                LodgeSchedule::insert($chunk);
            }

            if ($skipped > 0) {
                $this->command?->warn("Skipped {$skipped} lodges whose order (club type) is not set up.");
            }
        });
    }

    /**
     * The kind of source a listing URL is when it is not the province's own site, or null when it is.
     */
    private function thirdPartyKind(?string $url): ?string
    {
        return match ($url === null ? null : parse_url($url, PHP_URL_HOST)) {
            'onthesquare.co' => LodgeSource::DIRECTORY_PAGE,
            'catalogue.wmlmt.org.uk' => LodgeSource::CATALOGUE_PAGE,
            default => null,
        };
    }

    /**
     * @param  array<string, int>  $lodgesPerUrl
     */
    private function listingKind(?string $url, array $lodgesPerUrl): string
    {
        return ($lodgesPerUrl[$url] ?? 0) > 1 ? LodgeSource::PROVINCE_LIST : LodgeSource::PROVINCE_PAGE;
    }

    /**
     * Keep the lodge's links in step with the file: a new one is added, one that is no longer known is removed, and one
     * whose address changed loses its old check results. New and removed links are collected for one bulk write.
     *
     * @param  array<string, string|null>  $wanted  the address for each kind, null for none
     * @param  Collection<string, LodgeSource>  $existing  the lodge's links by kind
     * @param  list<array<string, mixed>>  $newSources
     * @param  list<int>  $oldSources
     */
    private function syncSources(Lodge $lodge, array $wanted, Collection $existing, array &$newSources, array &$oldSources, mixed $now): void
    {
        foreach ($wanted as $kind => $url) {
            $source = $existing->get($kind);

            if ($url === null || $url === '') {
                if ($source) {
                    $oldSources[] = $source->id;
                }
            } elseif (! $source) {
                $newSources[] = ['lodge_id' => $lodge->id, 'kind' => $kind, 'url' => $url, 'created_at' => $now, 'updated_at' => $now];
            } elseif ($source->url !== $url) {
                $source->forceFill(['url' => $url, 'last_checked_at' => null, 'last_status' => null, 'last_http_status' => null, 'content_hash' => null, 'changed_at' => null])->save();
            }
        }
    }

    /**
     * @return list<array<string, string|null>>
     */
    public function rows(?string $directory = null): array
    {
        $directory ??= database_path('data/lodges');
        $rows = [];

        foreach (glob($directory.'/*.csv') ?: [] as $file) {
            $handle = fopen($file, 'r');
            $header = fgetcsv($handle, escape: '');

            if ($header === false || array_diff($header, self::COLUMNS) !== []) {
                throw new RuntimeException(basename($file).' has an unknown or missing header row.');
            }

            while (($line = fgetcsv($handle, escape: '')) !== false) {
                if ($line === [null] || count($line) !== count($header)) {
                    continue;
                }

                $row = array_map(fn ($value) => trim($value) === '' ? null : trim($value), array_combine($header, $line));

                foreach (self::COLUMNS as $column) {
                    $row[$column] ??= null;
                }

                $row['slug'] = Lodge::slugFor((string) $row['name'], $row['number'], $row['order']);
                $rows[] = $row;
            }

            fclose($handle);
        }

        return $rows;
    }

    public static function normalisePostcode(string $postcode): string
    {
        return strtoupper(preg_replace('/\s+/', '', $postcode));
    }
}
