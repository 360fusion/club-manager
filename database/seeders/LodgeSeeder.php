<?php

namespace Database\Seeders;

use App\Models\ClubType;
use App\Models\Lodge;
use App\Models\LodgeSource;
use App\Models\MasonicHall;
use App\Models\Province;
use App\Support\MeetingScheduleParser;
use App\Support\Months;
use Illuminate\Database\Seeder;
use RuntimeException;

class LodgeSeeder extends Seeder
{
    /**
     * The columns a lodge CSV may carry. `order` is a `club_types.code`, `province_code` a
     * `provinces.code` and `hall_postcode` finds the hall the lodge meets in, or `hall_town` when only the town is known
     * (used only when the province has exactly one masonic hall in that town). `meets_text` is the
     * wording from the source, kept as written; the regular pattern is worked out from it.
     * `source_url` is the page the details were read from (a province page, or the directory's page where nothing better exists) and `directory_url` is an extra link to the lodge's page on a third-party directory. `installation_text` is a month, or the words for one; without it the month is read from
     * `meets_text` where the wording names one.
     */
    public const COLUMNS = [
        'province_code', 'order', 'number', 'name', 'hall_postcode', 'hall_town', 'meets_text',
        'installation_text', 'website_url', 'source_url', 'directory_url',
    ];

    /**
     * Load every CSV in database/data/lodges (one per province). Lodges are matched on their slug,
     * so re-running refreshes them from the files without creating duplicates. Claiming, website
     * and description are never touched, and a schedule a superadmin entered by hand is kept.
     */
    public function run(): void
    {
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

        foreach ($rows as $row) {
            if (! $types->has($row['order'])) {
                $skipped++;

                continue;
            }

            $provinceId = $provinces[$row['province_code']] ?? null;
            $sameTown = $provinceId && $row['hall_town'] ? $hallsByTown->get($provinceId.'|'.strtolower(trim($row['hall_town']))) : null;
            $hallId = $halls[self::normalisePostcode((string) $row['hall_postcode'])] ?? ($sameTown?->count() === 1 ? $sameTown->first()->id : null);

            $installation = Months::number($row['installation_text']) ?? ($row['meets_text'] ? $parser->installationMonth($row['meets_text']) : null);

            $lodge = Lodge::firstOrNew(['slug' => $row['slug']]);
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

            $fromDirectory = $this->isDirectoryUrl($row['source_url']);
            $this->saveSource($lodge, $fromDirectory ? LodgeSource::PROVINCE_PAGE : $this->listingKind($row['source_url'], $lodgesPerUrl), $fromDirectory ? null : $row['source_url']);
            $this->saveSource($lodge, LodgeSource::DIRECTORY_PAGE, $fromDirectory ? $row['source_url'] : $row['directory_url']);
            $this->saveSource($lodge, LodgeSource::UGLE_HALL, $hallUgleUrls[$lodge->masonic_hall_id] ?? null);

            $lodge->schedules()->where('source', 'import')->delete();
            $patterns = $row['meets_text'] ? $parser->parseAll($row['meets_text']) : [];

            if ($patterns !== [] && ! $lodge->schedules()->exists()) {
                // The installation is a meeting too. With one pattern its month joins that pattern; with
                // several, it is left alone unless one of them already covers it.
                if ($installation && count($patterns) === 1 && ! in_array($installation, $patterns[0]['months'], true)) {
                    $patterns[0]['months'] = [...$patterns[0]['months'], $installation];
                    sort($patterns[0]['months']);
                }

                foreach ($patterns as $pattern) {
                    $lodge->schedules()->create([...$pattern, 'masonic_hall_id' => $lodge->masonic_hall_id, 'source' => 'import']);
                }
            }
        }

        if ($skipped > 0) {
            $this->command?->warn("Skipped {$skipped} lodges whose order (club type) is not set up.");
        }
    }

    private function isDirectoryUrl(?string $url): bool
    {
        return $url !== null && parse_url($url, PHP_URL_HOST) === 'onthesquare.co';
    }

    /**
     * @param  array<string, int>  $lodgesPerUrl
     */
    private function listingKind(?string $url, array $lodgesPerUrl): string
    {
        return ($lodgesPerUrl[$url] ?? 0) > 1 ? LodgeSource::PROVINCE_LIST : LodgeSource::PROVINCE_PAGE;
    }

    /**
     * Keep the lodge's link of one kind in step with the file. The province link is either the
     * lodge's own page or a list page, never both. A link that changed loses its old check
     * results, and one that is no longer known is removed.
     */
    private function saveSource(Lodge $lodge, string $kind, ?string $url): void
    {
        $kinds = in_array($kind, LodgeSource::PROVINCE_KINDS, true) ? LodgeSource::PROVINCE_KINDS : [$kind];

        if ($url === null) {
            $lodge->sources()->whereIn('kind', $kinds)->delete();

            return;
        }

        $lodge->sources()->whereIn('kind', array_diff($kinds, [$kind]))->delete();

        $source = $lodge->sources()->firstOrNew(['kind' => $kind]);

        if ($source->exists && $source->url !== $url) {
            $source->forceFill(['last_checked_at' => null, 'last_status' => null, 'last_http_status' => null, 'content_hash' => null, 'changed_at' => null]);
        }

        $source->url = $url;
        $source->save();
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
