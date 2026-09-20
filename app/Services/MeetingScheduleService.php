<?php

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

class MeetingScheduleService
{
    private const DAY_MAP = [
        'Sunday' => Carbon::SUNDAY,
        'Monday' => Carbon::MONDAY,
        'Tuesday' => Carbon::TUESDAY,
        'Wednesday' => Carbon::WEDNESDAY,
        'Thursday' => Carbon::THURSDAY,
        'Friday' => Carbon::FRIDAY,
        'Saturday' => Carbon::SATURDAY,
    ];

    /**
     * Compute the exact date for an occurrence (1st, 2nd, 3rd, 4th, last) and weekday in a given month/year.
     */
    public function calculateNthWeekday(int $year, int $month, string $occurrence, string $dayOfWeek): Carbon
    {
        if (! isset(self::DAY_MAP[$dayOfWeek])) {
            throw new InvalidArgumentException("Invalid day of week: {$dayOfWeek}");
        }

        $targetDay = self::DAY_MAP[$dayOfWeek];
        $occurrence = strtolower($occurrence);

        if ($occurrence === 'last') {
            $date = Carbon::createFromDate($year, $month, 1)->endOfMonth();
            while ($date->dayOfWeek !== $targetDay) {
                $date->subDay();
            }

            return $date->startOfDay();
        }

        $nMap = ['1st' => 1, '2nd' => 2, '3rd' => 3, '4th' => 4];
        if (! isset($nMap[$occurrence])) {
            throw new InvalidArgumentException("Invalid occurrence: {$occurrence}");
        }

        $n = $nMap[$occurrence];

        $date = Carbon::createFromDate($year, $month, 1);
        while ($date->dayOfWeek !== $targetDay) {
            $date->addDay();
        }

        $date->addWeeks($n - 1);

        if ($date->month !== $month) {
            throw new InvalidArgumentException("Month {$month}/{$year} does not contain a {$occurrence} {$dayOfWeek}");
        }

        return $date->startOfDay();
    }

    /**
     * Batch generate season meeting dates given a list of active months.
     */
    public function generateSeasonDates(int $year, array $activeMonths, string $occurrence, string $dayOfWeek): array
    {
        $dates = [];
        foreach ($activeMonths as $month) {
            $dates[] = $this->calculateNthWeekday($year, (int) $month, $occurrence, $dayOfWeek);
        }

        return $dates;
    }
}
