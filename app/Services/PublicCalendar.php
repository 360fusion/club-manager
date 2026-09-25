<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Event;
use App\Models\User;
use App\Support\Currencies;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The events a visitor may see on a club's public calendar block: published, not cancelled and visible to
 * that viewer (guests get public events only, members also see their own club's). Times are shown exactly as
 * they were entered, like every other events page (the app stores an event's wall-clock time as given), and
 * the dates are worked out here so the grid never shifts with the visitor's own browser timezone.
 * Meetings and summonses are for members and are never included.
 */
class PublicCalendar
{
    public const MONTHS_BACK = 12;

    public const MONTHS_FORWARD = 24;

    public const UPCOMING_LIMIT = 20;

    public function __construct(private readonly Club $club, private readonly ?User $viewer) {}

    /**
     * @return array<string, mixed>
     */
    public function forMonth(?string $month): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $thisMonth = $today->startOfMonth();
        $first = $this->clampMonth($month, $thisMonth);
        $earliest = $thisMonth->subMonths(self::MONTHS_BACK);
        $latest = $thisMonth->addMonths(self::MONTHS_FORWARD);

        // A week either side of the month covers the leading and trailing days of the grid, whichever day the week starts on.
        $from = $first->subDays(7);
        $to = $first->endOfMonth()->addDays(7);

        $events = $this->query()
            ->where('starts_at', '<=', $to->toDateTimeString())
            ->where(fn (Builder $q) => $q
                ->where('ends_at', '>=', $from->toDateTimeString())
                ->orWhere(fn (Builder $single) => $single->whereNull('ends_at')->where('starts_at', '>=', $from->toDateTimeString())))
            ->orderBy('starts_at')
            ->get();

        $upcoming = $this->query()
            ->where(fn (Builder $q) => $q
                ->where('starts_at', '>=', $today->toDateTimeString())
                ->orWhere('ends_at', '>=', $today->toDateTimeString()))
            ->orderBy('starts_at')
            ->limit(self::UPCOMING_LIMIT)
            ->get();

        return [
            'month' => $first->format('Y-m'),
            'label' => $first->format('F Y'),
            'prev' => $first->gt($earliest) ? $first->subMonth()->format('Y-m') : null,
            'next' => $first->lt($latest) ? $first->addMonth()->format('Y-m') : null,
            'today' => $today->toDateString(),
            'today_month' => $thisMonth->format('Y-m'),
            'events' => $events->map(fn (Event $event) => $this->payload($event))->values()->all(),
            'upcoming' => $upcoming->map(fn (Event $event) => $this->payload($event))->values()->all(),
            'subscribe_url' => route('public.site.calendar_feed', ['clubSlug' => $this->club->slug]),
        ];
    }

    /**
     * @return Builder<Event>
     */
    private function query(): Builder
    {
        return $this->club->events()->getQuery()
            ->published()
            ->where('status', '!=', 'cancelled')
            ->visibleTo($this->viewer);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Event $event): array
    {
        $start = CarbonImmutable::instance($event->starts_at);
        $end = $event->ends_at ? CarbonImmutable::instance($event->ends_at) : $start;
        $price = (float) $event->price;

        return [
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'start_date' => $start->toDateString(),
            'end_date' => $end->lt($start) ? $start->toDateString() : $end->toDateString(),
            'time' => $start->format('H:i'),
            'location' => $event->formatted_location ?: null,
            'price' => $event->requires_payment && $price > 0 ? Currencies::format($price, $this->club) : null,
            'is_recurring' => (bool) $event->is_recurring,
        ];
    }

    private function clampMonth(?string $month, CarbonImmutable $thisMonth): CarbonImmutable
    {
        if (! is_string($month) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            return $thisMonth;
        }

        $requested = CarbonImmutable::createFromFormat('!Y-m', $month);

        if ($requested === false) {
            return $thisMonth;
        }

        $earliest = $thisMonth->subMonths(self::MONTHS_BACK);
        $latest = $thisMonth->addMonths(self::MONTHS_FORWARD);

        return $requested->lt($earliest) ? $earliest : ($requested->gt($latest) ? $latest : $requested);
    }
}
