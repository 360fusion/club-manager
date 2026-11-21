<?php

namespace App\Http\Controllers;

use App\Models\PageTiming;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * How long pages take on members' own devices: for each page, how it was opened (a first load, or a tap inside the
 * app), with the typical (median) and slow (75th percentile) times, over the last week or month.
 */
class SuperAdminPageSpeedController extends Controller
{
    public function index(Request $request): Response
    {
        $days = in_array((int) $request->query('days'), [1, 7, 30], true) ? (int) $request->query('days') : 7;
        $device = in_array($request->query('device'), ['phone', 'desktop'], true) ? $request->query('device') : null;

        $timings = PageTiming::where('created_at', '>=', now()->subDays($days))
            ->when($device, fn ($query) => $query->where('device', $device))
            ->get(['page', 'kind', 'ms', 'server_ms', 'cached']);

        $rows = $timings->groupBy(fn (PageTiming $timing) => $timing->page.'|'.$timing->kind)
            ->map(fn (Collection $group) => [
                'page' => $group->first()->page,
                'kind' => $group->first()->kind,
                'count' => $group->count(),
                'median' => self::percentile($group->pluck('ms'), 50),
                'p75' => self::percentile($group->pluck('ms'), 75),
                'server_median' => self::percentile($group->pluck('server_ms')->filter(fn ($ms) => $ms !== null), 50),
                'cached_share' => (int) round(100 * $group->where('cached', true)->count() / $group->count()),
            ])
            ->sortByDesc('p75')
            ->values();

        return Inertia::render('SuperAdmin/PageSpeed/Index', [
            'rows' => $rows,
            'totals' => [
                'count' => $timings->count(),
                'load_median' => self::percentile($timings->where('kind', PageTiming::LOAD)->pluck('ms'), 50),
                'visit_median' => self::percentile($timings->where('kind', PageTiming::VISIT)->pluck('ms'), 50),
                'visit_p75' => self::percentile($timings->where('kind', PageTiming::VISIT)->pluck('ms'), 75),
            ],
            'filters' => ['days' => $days, 'device' => $device ?? ''],
        ]);
    }

    /**
     * The value below which the given share of timings fall (nearest rank), or null with none.
     *
     * @param  Collection<int, mixed>  $values
     */
    private static function percentile(Collection $values, int $percent): ?int
    {
        $sorted = $values->map(fn ($value) => (int) $value)->sort()->values();

        return $sorted->isEmpty() ? null : $sorted[max(0, (int) ceil($percent / 100 * $sorted->count()) - 1)];
    }
}
