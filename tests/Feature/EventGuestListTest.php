<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\EventMenuItem;
use App\Models\User;
use App\Services\Events\EventRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventGuestListTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $admin;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->admin = $this->member('Admin', 'admin');
        $this->event = Event::create(['club_id' => $this->club->id, 'title' => 'Annual Dinner', 'slug' => 'dinner', 'starts_at' => now()->addWeek(), 'status' => 'upcoming', 'has_dining' => true, 'capacity' => 3, 'waitlist_enabled' => true]);
    }

    private function member(string $name, string $role = 'member'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $this->club->users()->attach($user->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    /**
     * @return array<string, EventMenuItem>
     */
    private function menu(): array
    {
        $dishes = [];

        foreach ([['starter', 'Soup', false], ['main', 'Beef', false], ['main', 'Nut Roast', true], ['dessert', 'Tart', false]] as [$course, $name, $vegan]) {
            $dishes[$name] = EventMenuItem::create(['event_id' => $this->event->id, 'category' => $course, 'name' => $name, 'is_vegetarian' => $vegan, 'is_vegan' => $vegan]);
        }

        return $dishes;
    }

    private function book(User $user, array $people): void
    {
        app(EventRegistrationService::class)->register($this->event, $user, ['attendees' => $people]);
    }

    private function seedBookings(): void
    {
        $menu = $this->menu();
        $meal = fn (string $main) => ['attending_dining' => true, 'starter_item_id' => $menu['Soup']->id, 'main_item_id' => $menu[$main]->id, 'dessert_item_id' => $menu['Tart']->id];

        $this->book($this->member('Alice Member'), [['name' => 'Alice Member', 'dietary_requirements' => 'Nut allergy', ...$meal('Beef')], ['name' => '=cmd|calc Guest', 'is_guest' => true, ...$meal('Nut Roast')]]);
        $this->book($this->member('Bob Member'), [['name' => 'Bob Member', ...$meal('Beef')]]);
        $this->book($this->member('Wendy Waiting'), [['name' => 'Wendy Waiting', ...$meal('Beef')]]);
    }

    public function test_the_printable_guest_list_shows_meals_dietary_needs_guests_and_the_waiting_list(): void
    {
        $this->seedBookings();

        $html = preg_replace('/\s+/', ' ', (string) $this->actingAs($this->admin)->get(route('admin.events.guest_list', ['clubSlug' => 'club-a', 'id' => $this->event->id]))->assertOk()->getContent());

        foreach (['Annual Dinner', 'Alice Member', 'Bob Member', 'Nut Roast', 'Nut allergy', 'GUEST', 'of Alice Member', 'Waiting list', 'Wendy Waiting', '3 people of 3 places'] as $expected) {
            $this->assertStringContainsString($expected, $html, "missing: {$expected}");
        }
    }

    public function test_the_guest_list_can_be_downloaded_as_a_pdf(): void
    {
        $this->seedBookings();

        $this->actingAs($this->admin)->get(route('admin.events.guest_list', ['clubSlug' => 'club-a', 'id' => $this->event->id, 'download' => 1]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_catering_summary_counts_portions_and_lists_dietary_notes(): void
    {
        $this->seedBookings();

        $html = (string) $this->actingAs($this->admin)->get(route('admin.events.catering', ['clubSlug' => 'club-a', 'id' => $this->event->id]))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<span class="n">3<\/span>dining/', $html);
        $this->assertMatchesRegularExpression('/Beef<\/td><td class="count">2</', $html);
        $this->assertMatchesRegularExpression('/Nut Roast<\/td><td class="count">1</', $html);
        $this->assertMatchesRegularExpression('/Soup<\/td><td class="count">3</', $html);
        $this->assertStringContainsString('Nut allergy', $html);
        $this->assertStringNotContainsString('Wendy Waiting', $html, 'waiting-list people are not catered for yet');
    }

    public function test_the_csv_export_has_one_row_per_person_and_neutralises_formulas(): void
    {
        $this->seedBookings();

        $csv = $this->actingAs($this->admin)->get(route('admin.events.export', ['clubSlug' => 'club-a', 'id' => $this->event->id]))->assertOk()->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));

        $this->assertCount(4, $lines, 'a header plus three people');
        $this->assertStringContainsString('Guest of', $lines[0]);
        $this->assertStringContainsString("'=cmd|calc Guest", $csv);
        $this->assertStringNotContainsString(',=cmd|calc', $csv);
        $this->assertStringNotContainsString('"=cmd|calc', $csv);
    }

    public function test_only_event_organisers_of_that_club_can_print_or_export(): void
    {
        $this->seedBookings();
        $member = User::where('name', 'Alice Member')->first();
        $otherClub = Club::create(['club_type_id' => $this->club->club_type_id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']);
        $otherAdmin = User::factory()->create();
        $otherClub->users()->attach($otherAdmin->id, ['role' => 'admin', 'status' => 'active']);

        foreach (['admin.events.guest_list', 'admin.events.catering', 'admin.events.export'] as $route) {
            $url = route($route, ['clubSlug' => 'club-a', 'id' => $this->event->id]);

            $this->actingAs($member)->get($url)->assertForbidden();
            $this->actingAs($otherAdmin)->get($url)->assertForbidden();
        }

        $this->actingAs($otherAdmin)->get(route('admin.events.export', ['clubSlug' => 'club-b', 'id' => $this->event->id]))->assertNotFound();
    }
}
