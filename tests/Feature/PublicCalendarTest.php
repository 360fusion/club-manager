<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\Page;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCalendarTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Lodge', 'code' => 'lodge']);
        $this->club = Club::create(['name' => 'Lodge of Fraternity', 'slug' => 'lodge-of-fraternity', 'club_type_id' => $type->id, 'email' => 'club@example.org']);

        Page::create(['club_id' => $this->club->id, 'title' => 'Diary', 'slug' => 'diary', 'is_published' => true, 'blocks' => [['type' => 'calendar', 'heading' => 'Diary']]]);
        Page::create(['club_id' => $this->club->id, 'title' => 'Plain', 'slug' => 'plain', 'is_published' => true, 'blocks' => [['type' => 'text', 'content' => '<p>Hi</p>']]]);
    }

    private function event(string $title, Visibility $visibility = Visibility::Public, string $status = 'upcoming', ?CarbonImmutable $start = null, array $extra = []): Event
    {
        return Event::create(array_merge([
            'club_id' => $this->club->id,
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'starts_at' => ($start ?? CarbonImmutable::now()->addDays(3))->utc(),
            'status' => $status,
            'visibility' => $visibility,
            'rsvp_audience' => Visibility::Club,
        ], $extra));
    }

    private function diary(?string $query = null): string
    {
        return route('public.site', ['clubSlug' => $this->club->slug, 'pageSlug' => 'diary']).($query ? '?'.$query : '');
    }

    /**
     * @return list<string>
     */
    private function upcomingTitles(?User $viewer = null): array
    {
        $test = $viewer ? $this->actingAs($viewer) : $this;
        $titles = [];

        $test->get($this->diary())->assertOk()->assertInertia(function ($page) use (&$titles) {
            $titles = collect($page->toArray()['props']['calendar']['upcoming'])->pluck('title')->all();
        });

        return $titles;
    }

    public function test_visitors_see_only_public_published_uncancelled_events(): void
    {
        $this->event('Open evening');
        $this->event('Draft dinner', status: 'draft');
        $this->event('Called off', status: 'cancelled');
        $this->event('Members supper', Visibility::Club);

        $this->assertSame(['Open evening'], $this->upcomingTitles());
    }

    public function test_active_members_also_see_their_clubs_events(): void
    {
        $this->event('Open evening');
        $this->event('Members supper', Visibility::Club, start: CarbonImmutable::now()->addDays(4));

        $member = User::factory()->create();
        $member->clubs()->attach($this->club->id, ['role' => 'member', 'status' => 'active']);

        $this->assertSame(['Open evening', 'Members supper'], $this->upcomingTitles($member));
    }

    public function test_the_month_is_validated_and_clamped(): void
    {
        $thisMonth = CarbonImmutable::now()->format('Y-m');

        foreach (['garbage', '2026-13', ''] as $bad) {
            $this->get($this->diary('cal='.$bad))->assertOk()->assertInertia(fn ($page) => $page->where('calendar.month', $thisMonth));
        }

        $this->get($this->diary('cal=1999-01'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('calendar.month', CarbonImmutable::now()->startOfMonth()->subMonths(12)->format('Y-m'))
            ->where('calendar.prev', null));

        $this->get($this->diary('cal=2999-01'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('calendar.month', CarbonImmutable::now()->startOfMonth()->addMonths(24)->format('Y-m'))
            ->where('calendar.next', null));
    }

    public function test_a_month_lists_its_events_with_the_time_as_entered(): void
    {
        $start = CarbonImmutable::now()->addDays(2)->setTime(19, 30);
        $this->event('Late meeting', start: $start, extra: ['ends_at' => $start->addHours(2), 'location' => 'The Hall']);

        $this->get($this->diary('cal='.$start->format('Y-m')))->assertOk()->assertInertia(fn ($page) => $page
            ->where('calendar.events.0.start_date', $start->toDateString())
            ->where('calendar.events.0.time', '19:30')
            ->where('calendar.events.0.location', 'The Hall'));
    }

    public function test_a_page_without_a_calendar_block_gets_no_calendar(): void
    {
        $this->event('Open evening');

        $this->get(route('public.site', ['clubSlug' => $this->club->slug, 'pageSlug' => 'plain']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('calendar', null));
    }

    public function test_the_subscribe_feed_lists_only_public_events(): void
    {
        $this->event('Open evening', extra: ['location' => 'The Hall']);
        $this->event('Draft dinner', status: 'draft');
        $this->event('Called off', status: 'cancelled');
        $this->event('Members supper', Visibility::Club);

        $response = $this->get(route('public.site.calendar_feed', ['clubSlug' => $this->club->slug]))->assertOk();

        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $body = $response->getContent();
        $this->assertStringContainsString('SUMMARY:Open evening', $body);
        $this->assertStringContainsString('LOCATION:The Hall', $body);
        $this->assertStringNotContainsString('Draft dinner', $body);
        $this->assertStringNotContainsString('Called off', $body);
        $this->assertStringNotContainsString('Members supper', $body);
    }

    public function test_draft_events_no_longer_appear_in_the_upcoming_events_list(): void
    {
        $this->event('Open evening');
        $this->event('Draft dinner', status: 'draft');

        $this->get(route('public.site', ['clubSlug' => $this->club->slug, 'pageSlug' => 'plain']))->assertInertia(fn ($page) => $page
            ->has('upcomingEvents', 1)
            ->where('upcomingEvents.0.title', 'Open evening'));
    }
}
