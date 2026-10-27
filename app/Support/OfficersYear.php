<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The heading above a summons' list of officers: "OFFICERS FOR 2026-2027". A lodge's officers serve from one installation
 * to the next, so the year a date belongs to depends on the month the lodge installs in.
 */
class OfficersYear
{
    /**
     * @param  string|null  $installationMonth  the lodge's installation month by name ("October"), or null when it has not said
     */
    public static function label(CarbonInterface $date, ?string $installationMonth): string
    {
        // A lodge that has not named its installation month gets the calendar year the date falls in.
        $start = Months::number($installationMonth) ?? 1;
        $startYear = $date->month >= $start ? $date->year : $date->year - 1;

        return $start === 1
            ? 'OFFICERS FOR '.$startYear
            : 'OFFICERS FOR '.$startYear.'-'.($startYear + 1);
    }
}
