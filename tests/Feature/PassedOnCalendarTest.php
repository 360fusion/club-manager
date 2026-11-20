<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Lodge;
use App\Models\LodgeSchedule;
use App\Models\MasonicHall;
use App\Models\PassedOnDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PassedOnCalendarTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Lodge $sister;

    private User $tom;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();
        $this->travelTo('2026-09-20 10:00');
        $hall = MasonicHall::factory()->create();
        $this->club = Club::factory()->create();
        Lodge::factory()->create(['club_id' => $this->club->id, 'masonic_hall_id' => $hall->id]);
        $this->sister = Lodge::factory()->create(['masonic_hall_id' => $hall->id]);
        $secretary = User::factory()->create();
        $this->club->users()->attach($secretary->id, ['role' => 'secretary', 'status' => 'active']);
        $this->tom = User::factory()->create();
        $this->club->users()->attach($this->tom->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($secretary)->post(route('member.pass_on.store', $this->club->slug), [
            'kind' => 'sister', 'type' => 'summons', 'lodge_slug' => $this->sister->slug, 'title' => 'Installation meeting',
            'body' => 'All welcome', 'meeting_date' => '2026-10-06', 'meeting_time' => '18:00', 'venue' => 'The Masonic Hall',
        ])->assertSessionHasNoErrors();
    }

    /** @return list<array<string, mixed>> */
    private function october(): array
    {
        return json_decode(json_encode($this->actingAs($this->tom)->get(route('members.calendar', ['month' => '2026-10']))->assertOk()->viewData('page')['props']['items']), true);
    }

    public function test_a_passed_on_summons_is_in_the_calendar_and_replaces_the_expected_meeting(): void
    {
        $this->tom->followedLodges()->attach($this->sister->id, ['in_calendar' => true, 'notify_summons' => false]);
        LodgeSchedule::factory()->create(['lodge_id' => $this->sister->id, 'occurrence' => '1st', 'day_of_week' => 'Tuesday', 'months' => [10], 'start_time' => '19:00']);

        $onTheDay = collect($this->october())->filter(fn (array $item) => str_starts_with($item['start'], '2026-10-06'));

        $this->assertCount(1, $onTheDay);
        $item = $onTheDay->first();
        $this->assertSame('passed_on', $item['type']);
        $this->assertSame('Installation meeting', $item['title']);
        $this->assertSame('2026-10-06T18:00:00+01:00', $item['start']);
        $this->assertSame('The Masonic Hall', $item['where']);
        $this->assertSame(route('members.passed_on.show', PassedOnDelivery::where('user_id', $this->tom->id)->value('id'), false), $item['url']);
    }

    public function test_it_stays_out_when_the_member_turned_the_calendar_off_or_muted_the_lodge(): void
    {
        $this->tom->forceFill(['passed_on_prefs' => ['calendar' => false]])->save();
        $this->assertEmpty(collect($this->october())->where('type', 'passed_on'));

        $this->tom->forceFill(['passed_on_prefs' => ['muted_lodges' => [$this->sister->id]]])->save();
        $this->assertEmpty(collect($this->october())->where('type', 'passed_on'));

        $this->tom->forceFill(['passed_on_prefs' => null])->save();
        $this->assertCount(1, collect($this->october())->where('type', 'passed_on'));
    }

    public function test_a_muted_lodge_followed_in_the_calendar_shows_no_expected_dates(): void
    {
        $this->tom->followedLodges()->attach($this->sister->id, ['in_calendar' => true, 'notify_summons' => false]);
        LodgeSchedule::factory()->create(['lodge_id' => $this->sister->id, 'occurrence' => '3rd', 'day_of_week' => 'Tuesday', 'months' => [10], 'start_time' => '19:00']);
        $this->tom->forceFill(['passed_on_prefs' => ['muted_lodges' => [$this->sister->id]]])->save();

        $this->assertEmpty(collect($this->october())->whereIn('type', ['lodge', 'passed_on']));
    }
}
