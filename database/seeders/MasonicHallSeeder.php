<?php

namespace Database\Seeders;

use App\Models\MasonicHall;
use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class MasonicHallSeeder extends Seeder
{
    /**
     * The columns a hall CSV may carry. `province_code` is the `provinces.code` the hall belongs to
     * and `slug` is optional (it defaults to the hall name and town). `kind` is one of
     * MasonicHall::KINDS and defaults to a masonic hall.
     */
    public const COLUMNS = [
        'province_code', 'slug', 'name', 'kind', 'address_line_1', 'address_line_2', 'town', 'county',
        'postcode', 'country', 'telephone', 'email', 'website_url', 'source_url',
    ];

    /**
     * Load every CSV in database/data/masonic_halls (one file per province). Halls are matched on
     * their slug, so re-running refreshes the address details from the file without creating
     * duplicates. A province that a superadmin has already set on a hall is left alone.
     */
    public function run(): void
    {
        $provinceIds = Province::pluck('id', 'code');

        foreach ($this->rows() as $row) {
            $provinceCode = $row['province_code'];
            unset($row['province_code']);

            $hall = MasonicHall::firstOrNew(['slug' => $row['slug']]);
            $hall->fill($row);

            if ($hall->province_id === null && $provinceCode !== '' && $provinceIds->has($provinceCode)) {
                $hall->province_id = $provinceIds[$provinceCode];
            }

            $hall->save();
        }
    }

    /**
     * @return list<array<string, string|null>>
     */
    public function rows(?string $directory = null): array
    {
        $directory ??= database_path('data/masonic_halls');
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
                $row['province_code'] ??= '';
                $row['slug'] ??= Str::slug(trim($row['name'].' '.($row['town'] ?? '')));
                $row['kind'] ??= 'hall';

                if (! array_key_exists($row['kind'], MasonicHall::KINDS)) {
                    throw new RuntimeException(basename($file)." has an unknown kind '{$row['kind']}' for {$row['name']}.");
                }

                $rows[] = $row;
            }

            fclose($handle);
        }

        return $rows;
    }
}
