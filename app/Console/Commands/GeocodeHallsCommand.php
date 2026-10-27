<?php

namespace App\Console\Commands;

use App\Models\MasonicHall;
use App\Services\Geocoder;
use Illuminate\Console\Command;

/**
 * Looks up where each hall is from its postcode (OpenStreetMap, one request a second, cached), for the directory's
 * distance search. Only halls without a position are looked up, so it can be run again after halls are added.
 */
class GeocodeHallsCommand extends Command
{
    protected $signature = 'halls:geocode {--limit= : Look up at most this many halls}';

    protected $description = 'Store the position of each masonic hall that has a postcode but no position yet (about one a second).';

    public function handle(Geocoder $geocoder): int
    {
        $halls = MasonicHall::whereNull('latitude')->whereNotNull('postcode')->where('postcode', '!=', '')
            ->orderBy('id')
            ->when($this->option('limit'), fn ($query, $limit) => $query->limit((int) $limit))
            ->get();

        $found = 0;

        foreach ($halls as $hall) {
            $result = $geocoder->search($hall->postcode.($hall->town ? ', '.$hall->town : ''), 1, MasonicHall::SEARCH_COUNTRIES);
            $place = $result['results'][0] ?? null;

            if ($place === null) {
                $this->warn("Not found: {$hall->name} ({$hall->postcode})");

                continue;
            }

            $hall->update(['latitude' => $place['lat'], 'longitude' => $place['lng']]);
            $found++;
        }

        $this->info("Placed {$found} of {$halls->count()} hall(s).");

        return self::SUCCESS;
    }
}
