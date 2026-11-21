<?php

namespace Tests\Feature;

use App\Models\PageTiming;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageSpeedTest extends TestCase
{
    use RefreshDatabase;

    private function timing(array $overrides = []): array
    {
        return ['page' => 'Members/Calendar', 'kind' => 'visit', 'ms' => 120, 'server_ms' => 90, 'cached' => false, 'device' => 'phone', 'installed' => true, ...$overrides];
    }

    public function test_a_signed_in_members_device_sends_its_page_timings_and_nothing_about_them(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('members.page_timings.store'), ['timings' => [$this->timing(), $this->timing(['kind' => 'load', 'page' => 'Members/Dashboard', 'ms' => 900])]])
            ->assertNoContent();

        $this->assertSame(2, PageTiming::count());
        $this->assertEqualsCanonicalizing(['id', 'page', 'kind', 'ms', 'server_ms', 'cached', 'device', 'installed', 'created_at'], array_keys(PageTiming::first()->getAttributes()));
    }

    public function test_timings_are_checked_and_strangers_cannot_send_any(): void
    {
        $this->postJson(route('members.page_timings.store'), ['timings' => [$this->timing()]])->assertUnauthorized();

        $this->actingAs(User::factory()->create());
        $this->postJson(route('members.page_timings.store'), ['timings' => [$this->timing(['page' => '<script>'])]])->assertJsonValidationErrors('timings.0.page');
        $this->postJson(route('members.page_timings.store'), ['timings' => [$this->timing(['kind' => 'other'])]])->assertJsonValidationErrors('timings.0.kind');
        $this->postJson(route('members.page_timings.store'), ['timings' => array_fill(0, 21, $this->timing())])->assertJsonValidationErrors('timings');

        $this->assertSame(0, PageTiming::count());
    }

    public function test_the_report_gives_each_page_its_typical_and_slow_times_slowest_first(): void
    {
        foreach ([100, 200, 300, 400] as $ms) {
            PageTiming::factory()->create(['page' => 'Members/Calendar', 'ms' => $ms, 'cached' => $ms === 100]);
        }
        PageTiming::factory()->create(['page' => 'Members/Lodges', 'ms' => 2000]);
        PageTiming::factory()->create(['page' => 'Members/Old', 'ms' => 50])->forceFill(['created_at' => now()->subDays(10)])->save();

        $this->actingAs(User::factory()->create(['is_super_admin' => true]))->get(route('superadmin.page_speed.index'))
            ->assertInertia(fn ($page) => $page
                ->component('SuperAdmin/PageSpeed/Index')
                ->has('rows', 2)
                ->where('rows.0.page', 'Members/Lodges')
                ->where('rows.1.page', 'Members/Calendar')
                ->where('rows.1.median', 200)
                ->where('rows.1.p75', 300)
                ->where('rows.1.cached_share', 25)
                ->where('totals.count', 5));

        $this->actingAs(User::factory()->create())->get(route('superadmin.page_speed.index'))->assertRedirect();
    }

    public function test_timings_older_than_ninety_days_are_pruned(): void
    {
        PageTiming::factory()->create()->forceFill(['created_at' => now()->subDays(91)])->save();
        PageTiming::factory()->create();

        $this->artisan('model:prune', ['--model' => [PageTiming::class]])->assertSuccessful();

        $this->assertSame(1, PageTiming::count());
    }
}
