<?php

namespace Database\Seeders;

use App\Models\ClubType;
use App\Models\Lodge;
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
     * `installation_text` is a month, or the words for one; without it the month is read from
     * `meets_text` where the wording names one.
     */
    public const COLUMNS = [
        'province_code', 'order', 'number', 'name', 'hall_postcode', 'hall_town', 'meets_text',
        'installation_text', 'website_url', 'source_url',
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
        $parser = new MeetingScheduleParser;
        $skipped = 0;

        foreach ($this->rows() as $row) {
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

            $lodge->schedules()->where('source', 'import')->delete();
            $pattern = $row['meets_text'] ? $parser->parse($row['meets_text']) : null;

            if ($pattern && ! $lodge->schedules()->exists()) {
                // The installation is a meeting too, so its month is one of the meeting months.
                if ($installation && ! in_array($installation, $pattern['months'], true)) {
                    $pattern['months'] = [...$pattern['months'], $installation];
                    sort($pattern['months']);
                }

                $lodge->schedules()->create([...$pattern, 'masonic_hall_id' => $lodge->masonic_hall_id, 'source' => 'import']);
            }
        }

        if ($skipped > 0) {
            $this->command?->warn("Skipped {$skipped} lodges whose order (club type) is not set up.");
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
