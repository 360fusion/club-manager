<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MemberCalendar;
use App\Support\IcsCalendar;
use App\Support\MemberScope;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MemberCalendarController extends Controller
{
    public function __construct(private readonly MemberCalendar $calendar) {}

    public function show(Request $request, ?string $slug = null): Response
    {
        $scope = MemberScope::fromRequest($request, $slug);

        $month = $this->month($request->query('month'));
        $from = $month->startOfMonth()->startOfWeek();
        $to = $month->endOfMonth()->endOfWeek();

        $items = $this->calendar->items($scope, $from, $to)->map(fn (array $item) => [
            ...$item,
            'start' => $item['start']->toIso8601String(),
            'end' => $item['end']->toIso8601String(),
        ]);

        $user = $scope->user;
        $token = $this->tokenFor($user);

        return Inertia::render('Members/Calendar', [
            'month' => $month->format('Y-m'),
            'monthLabel' => $month->format('F Y'),
            'gridStart' => $from->toDateString(),
            'gridEnd' => $to->toDateString(),
            'items' => $items->values()->all(),
            'scopeClub' => $scope->club ? ['name' => $scope->club->name, 'slug' => $scope->club->slug, 'colour' => $scope->club->colourKey()] : null,
            'clubOptions' => $scope->memberClubs->map(fn ($club) => ['name' => $club->name, 'slug' => $club->slug, 'colour' => $club->colourKey()])->values()->all(),
            'feedUrl' => route('members.calendar.feed', ['token' => $token]),
        ]);
    }

    /**
     * The personal iCal feed. The secret token in the URL is the credential, so
     * calendar apps can subscribe without signing in.
     */
    public function feed(string $token): HttpResponse
    {
        $user = User::where('calendar_token', $token)->firstOrFail();
        $scope = MemberScope::for($user);

        $ics = new IcsCalendar('ClubManager', parse_url(config('app.url'), PHP_URL_HOST) ?: 'clubmanager');

        $items = $this->calendar->items($scope, CarbonImmutable::now()->subMonth(), CarbonImmutable::now()->addMonths(12));

        foreach ($items as $item) {
            $ics->add(
                $item['key'],
                $item['club']['name'].': '.$item['title'],
                $item['start'],
                $item['end'],
                $item['where'],
                null,
                url($item['url']),
            );
        }

        return response($ics->render(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="clubmanager.ics"',
            'Cache-Control' => 'private, max-age=900',
        ]);
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['calendar_token' => Str::random(48)])->save();

        return back()->with('success', 'Your calendar link has been replaced. Add the new link to your calendar app.');
    }

    private function tokenFor(User $user): string
    {
        if (! $user->calendar_token) {
            $user->forceFill(['calendar_token' => Str::random(48)])->save();
        }

        return $user->calendar_token;
    }

    private function month(mixed $value): CarbonImmutable
    {
        if (is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
            return CarbonImmutable::createFromFormat('Y-m-d', $value.'-01')->startOfDay();
        }

        return CarbonImmutable::now()->startOfMonth();
    }
}
