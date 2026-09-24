<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Lodge;
use App\Models\LodgeSchedule;
use App\Models\LodgeSource;
use App\Models\MasonicHall;
use App\Models\Province;
use App\Services\Lodges\LodgeClaimService;
use App\Support\MeetingScheduleParser;
use App\Support\Months;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Superadmin management of the lodge directory, the same shape as the Masonic Halls page.
 */
class SuperAdminLodgeController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:60',
            'order' => 'nullable|string|max:60',
            'status' => ['nullable', Rule::in([...Lodge::STATUSES, 'managed'])],
            'gaps' => 'nullable|in:no_hall,no_schedule,no_province',
        ]);

        $query = Lodge::query()->with(['clubType:id,code,name', 'province:id,name', 'masonicHall:id,name,town', 'schedules', 'sources', 'club:id,name,slug']);

        if ($q = trim((string) ($filters['q'] ?? ''))) {
            $like = '%'.$q.'%';
            $query->where(fn ($match) => $match->whereLike('name', $like)->orWhere('number', $q)
                ->orWhereHas('masonicHall', fn ($hall) => $hall->whereLike('name', $like)->orWhereLike('town', $like)->orWhereLike('postcode', $like)));
        }

        if (! empty($filters['province'])) {
            $query->whereHas('province', fn ($province) => $province->where('code', $filters['province']));
        }

        if (! empty($filters['order'])) {
            $query->whereHas('clubType', fn ($type) => $type->where('code', $filters['order']));
        }

        match ($filters['status'] ?? null) {
            null, '' => null,
            'managed' => $query->whereNotNull('club_id'),
            default => $query->where('status', $filters['status']),
        };

        match ($filters['gaps'] ?? null) {
            'no_hall' => $query->whereNull('masonic_hall_id'),
            'no_province' => $query->whereNull('province_id'),
            'no_schedule' => $query->whereDoesntHave('schedules'),
            default => null,
        };

        return Inertia::render('SuperAdmin/Lodges/Index', [
            'lodges' => $query->orderBy('name')->paginate(50)->withQueryString()->through(fn (Lodge $lodge) => [
                'id' => $lodge->id,
                'name' => $lodge->name,
                'display_name' => $lodge->displayName(),
                'number' => $lodge->number,
                'slug' => $lodge->slug,
                'status' => $lodge->status,
                'club_type_id' => $lodge->club_type_id,
                'order' => $lodge->clubType?->name,
                'province_id' => $lodge->province_id,
                'province' => $lodge->province?->name,
                'masonic_hall_id' => $lodge->masonic_hall_id,
                'hall' => $lodge->masonicHall ? $lodge->masonicHall->name.($lodge->masonicHall->town ? ', '.$lodge->masonicHall->town : '') : null,
                'meets_text' => $lodge->meets_text,
                'installation_month' => $lodge->installation_month,
                'website_url' => $lodge->website_url,
                'description' => $lodge->description,
                'is_managed' => $lodge->isManaged(),
                'club' => $lodge->club ? ['name' => $lodge->club->name, 'slug' => $lodge->club->slug] : null,
                'schedule' => $lodge->schedules->first()?->only(['occurrence', 'day_of_week', 'months', 'start_time', 'source']),
                'schedules' => $lodge->schedules->map->only(['occurrence', 'day_of_week', 'months', 'start_time'])->values(),
                'sources' => $lodge->sources->sortBy(fn ($source) => LodgeSource::TIERS[$source->kind] ?? 3)->map(fn ($source) => [
                    'label' => $source->label(),
                    'url' => $source->url,
                    'last_checked_at' => $source->last_checked_at?->diffForHumans(),
                    'last_status' => $source->last_status,
                    'changed_at' => $source->changed_at?->toDateString(),
                ])->values(),
            ]),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'province' => $filters['province'] ?? '',
                'order' => $filters['order'] ?? '',
                'status' => $filters['status'] ?? '',
                'gaps' => $filters['gaps'] ?? '',
            ],
            'provinces' => Province::orderBy('name')->get(['id', 'code', 'name']),
            'orders' => ClubType::whereHas('lodges')->orderBy('name')->get(['id', 'code', 'name']),
            'allOrders' => ClubType::orderBy('name')->get(['id', 'name']),
            'halls' => MasonicHall::orderBy('name')->get(['id', 'province_id', 'name', 'town']),
            'linkableClubs' => Club::whereDoesntHave('lodge')->orderBy('name')->limit(500)->get(['id', 'name']),
            'statuses' => Lodge::STATUSES,
            'months' => Months::NAMES,
            'occurrences' => LodgeSchedule::OCCURRENCES,
            'days' => LodgeSchedule::DAYS,
            'totals' => [
                'all' => Lodge::count(),
                'managed' => Lodge::whereNotNull('club_id')->count(),
                'no_hall' => Lodge::whereNull('masonic_hall_id')->count(),
                'no_schedule' => Lodge::whereDoesntHave('schedules')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $type = ClubType::findOrFail($validated['club_type_id']);
        $base = Lodge::slugFor($validated['name'], $validated['number'] ?? null, $type->code) ?: 'lodge';
        $slug = $base;

        for ($i = 2; Lodge::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        $lodge = Lodge::create([...$validated['lodge'], 'slug' => $slug]);
        $this->saveSchedule($lodge, $validated);

        return redirect()->route('superadmin.lodges.index')->with('success', "'{$lodge->displayName()}' added.");
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $lodge = Lodge::findOrFail($id);
        $validated = $this->validated($request);

        // The slug stays put so a lodge's address never changes when it is renamed.
        $lodge->update($validated['lodge']);
        $this->saveSchedule($lodge, $validated);

        return redirect()->route('superadmin.lodges.index')->with('success', "'{$lodge->displayName()}' updated.");
    }

    public function linkClub(Request $request, int $id, LodgeClaimService $claims): RedirectResponse
    {
        $validated = $request->validate(['club_id' => 'required|exists:clubs,id']);
        $lodge = Lodge::findOrFail($id);

        $claims->link($lodge, Club::findOrFail($validated['club_id']));

        return back()->with('success', "'{$lodge->displayName()}' is now linked to its club.");
    }

    public function unlinkClub(Request $request, int $id, LodgeClaimService $claims): RedirectResponse
    {
        $lodge = Lodge::findOrFail($id);

        $claims->unlink($lodge, $request->user());

        return back()->with('success', "'{$lodge->displayName()}' is no longer linked to a club. The club and its data are untouched.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $lodge = Lodge::findOrFail($id);

        if ($lodge->isManaged()) {
            return back()->with('error', "'{$lodge->displayName()}' is managed by a club account, so it cannot be deleted. Set its status to erased instead.");
        }

        $name = $lodge->displayName();
        $lodge->delete();

        return redirect()->route('superadmin.lodges.index')->with('success', "'{$name}' deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'club_type_id' => 'required|exists:club_types,id',
            'province_id' => 'nullable|exists:provinces,id',
            'masonic_hall_id' => 'nullable|exists:masonic_halls,id',
            'name' => 'required|string|max:255',
            'number' => 'nullable|string|max:20',
            'status' => ['sometimes', Rule::in(Lodge::STATUSES)],
            'meets_text' => 'nullable|string|max:2000',
            'installation_month' => 'nullable|integer|between:1,12',
            'website_url' => ['nullable', 'url', 'max:255', 'regex:#^https?://#i'],
            'description' => 'nullable|string|max:5000',
            'schedule' => 'nullable|array|max:5',
            'schedule.occurrence' => ['nullable', Rule::in(LodgeSchedule::OCCURRENCES)],
            'schedule.day_of_week' => ['nullable', Rule::in(LodgeSchedule::DAYS)],
            'schedule.months' => 'nullable|array|max:12',
            'schedule.months.*' => 'integer|between:1,12',
            'schedule.start_time' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'reparse' => 'nullable|boolean',
        ]);

        return [
            ...$data,
            'lodge' => collect($data)->only(['club_type_id', 'province_id', 'masonic_hall_id', 'name', 'number', 'status', 'meets_text', 'installation_month', 'website_url', 'description'])->all(),
        ];
    }

    /**
     * A schedule typed in by hand replaces whatever was there. "Read from the wording" runs the
     * parser again on the lodge's meeting text. Neither touching gives the current one back.
     *
     * @param  array<string, mixed>  $validated
     */
    private function saveSchedule(Lodge $lodge, array $validated): void
    {
        $schedule = $validated['schedule'] ?? null;

        if (! empty($validated['reparse'])) {
            $patterns = $lodge->meets_text ? (new MeetingScheduleParser)->parseAll($lodge->meets_text) : [];
            $lodge->schedules()->delete();

            foreach ($patterns as $pattern) {
                if (count($patterns) === 1) {
                    $pattern['months'] = $this->withInstallationMonth($pattern['months'], $lodge);
                }

                $lodge->schedules()->create([...$pattern, 'masonic_hall_id' => $lodge->masonic_hall_id, 'source' => 'import']);
            }

            return;
        }

        if ($schedule === null) {
            return;
        }

        $lodge->schedules()->delete();

        if (! empty($schedule['occurrence']) && ! empty($schedule['day_of_week']) && ! empty($schedule['months'])) {
            $months = $this->withInstallationMonth(array_values(array_unique(array_map('intval', $schedule['months']))), $lodge);

            $lodge->schedules()->create([
                'occurrence' => $schedule['occurrence'],
                'day_of_week' => $schedule['day_of_week'],
                'months' => $months,
                'start_time' => $schedule['start_time'] ?? null,
                'masonic_hall_id' => $lodge->masonic_hall_id,
                'source' => 'manual',
            ]);
        }
    }

    /**
     * The installation is a meeting too, so its month is always one of the meeting months.
     *
     * @param  list<int>  $months
     * @return list<int>
     */
    private function withInstallationMonth(array $months, Lodge $lodge): array
    {
        if ($lodge->installation_month && ! in_array((int) $lodge->installation_month, $months, true)) {
            $months[] = (int) $lodge->installation_month;
        }

        sort($months);

        return $months;
    }
}
