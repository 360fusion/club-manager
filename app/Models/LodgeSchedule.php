<?php

namespace App\Models;

use App\Services\MeetingScheduleService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * A regular meeting pattern, such as "first Tuesday of Sep to Jun". Dates are worked out from it
 * rather than stored, so they are expected dates, not a published summons.
 */
class LodgeSchedule extends Model
{
    use HasFactory;

    public const OCCURRENCES = ['1st', '2nd', '3rd', '4th', 'last'];

    /**
     * Lodge meeting times are local times, and every province is on UK time.
     */
    public const TIMEZONE = 'Europe/London';

    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    protected $fillable = [
        'lodge_id',
        'masonic_hall_id',
        'kind',
        'occurrence',
        'day_of_week',
        'months',
        'start_time',
        'source',
    ];

    protected function casts(): array
    {
        return ['months' => 'array'];
    }

    /**
     * @return BelongsTo<Lodge, $this>
     */
    public function lodge(): BelongsTo
    {
        return $this->belongsTo(Lodge::class);
    }

    /**
     * @return BelongsTo<MasonicHall, $this>
     */
    public function masonicHall(): BelongsTo
    {
        return $this->belongsTo(MasonicHall::class);
    }

    /**
     * The moment a meeting on $date starts, or null when the lodge lists no time.
     */
    public function startsOn(CarbonInterface $date): ?CarbonImmutable
    {
        return $this->start_time
            ? CarbonImmutable::parse($date->toDateString(), self::TIMEZONE)->setTimeFromTimeString($this->start_time)
            : null;
    }

    /**
     * The expected meeting dates from $from up to and including $to.
     *
     * @return list<CarbonImmutable>
     */
    public function datesBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        $service = app(MeetingScheduleService::class);
        $from = CarbonImmutable::parse($from->toDateString());
        $to = CarbonImmutable::parse($to->toDateString());
        $dates = [];

        for ($month = $from->startOfMonth(); $month <= $to; $month = $month->addMonth()) {
            if (! in_array($month->month, array_map('intval', $this->months ?? []), true)) {
                continue;
            }

            try {
                $date = CarbonImmutable::instance($service->calculateNthWeekday($month->year, $month->month, $this->occurrence, $this->day_of_week));
            } catch (InvalidArgumentException) {
                continue;
            }

            if ($date >= $from && $date <= $to) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    /**
     * The next $count expected dates on or after $from, looking up to a year ahead.
     *
     * @return list<CarbonImmutable>
     */
    public function nextDates(CarbonInterface $from, int $count = 3): array
    {
        return array_slice($this->datesBetween($from, CarbonImmutable::parse($from->toDateString())->addYear()), 0, $count);
    }
}
