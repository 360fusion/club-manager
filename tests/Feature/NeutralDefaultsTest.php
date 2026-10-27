<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\MasonicHall;
use App\Models\Meeting;
use App\Models\User;
use App\Support\OfficersYear;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A lodge that has not filled something in must see it empty, never another lodge's name, address, bank account or
 * officers, which would be saved and printed as its own the first time the form is submitted.
 */
class NeutralDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => ['meetings'], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Blank Lodge', 'slug' => 'blank-lodge', 'status' => 'active']);
        $this->admin = User::factory()->create();
        $this->club->users()->attach($this->admin->id, ['role' => 'admin', 'status' => 'active']);
    }

    public function test_the_settings_screen_starts_with_no_borrowed_identity_details(): void
    {
        $this->actingAs($this->admin)->get(route('admin.settings.show', ['clubSlug' => 'blank-lodge']))
            ->assertOk()
            ->assertInertia(function ($page) {
                $page->component('Admin/Settings/Show');

                foreach (['phone', 'address', 'address_line_1', 'city', 'postcode', 'social_facebook', 'tax_registration_number', 'bank_sort_code', 'bank_account_number', 'provincial_name', 'provincial_grand_master', 'deputy_provincial_grand_master', 'lodge_number', 'consecration_date', 'meeting_formula', 'membership_year_start', 'officers_year_label'] as $key) {
                    $page->where("settings.{$key}", '');
                }

                $page->where('settings.officers_roster', []);
            });
    }

    public function test_a_new_meeting_is_held_at_the_lodges_own_hall_or_nowhere(): void
    {
        $url = route('admin.meetings.create', ['clubSlug' => 'blank-lodge']);

        $this->actingAs($this->admin)->get($url)->assertInertia(fn ($page) => $page->where('meeting.venue', ''));

        $hall = MasonicHall::create(['name' => 'Freemasons Hall', 'slug' => 'freemasons-hall', 'address_line_1' => '1 High Street', 'town' => 'Appleby']);
        $this->club->update(['masonic_hall_id' => $hall->id]);

        $this->actingAs($this->admin)->get($url)->assertInertia(fn ($page) => $page->where('meeting.venue', 'Freemasons Hall, 1 High Street, Appleby'));
    }

    public function test_the_summons_cover_prints_no_provincial_officers_or_lodge_number_that_were_never_entered(): void
    {
        $meeting = Meeting::create(['club_id' => $this->club->id, 'title' => 'Meeting', 'meeting_date' => '2026-10-20', 'starts_at' => '18:30', 'venue' => 'Hall', 'dress_code' => 'Suit', 'rsvp_cutoff_at' => now()->addDays(10)]);

        $this->actingAs($this->admin)->get(route('admin.meetings.pdf', ['clubSlug' => 'blank-lodge', 'id' => $meeting->id]))
            ->assertOk()
            ->assertDontSee('Provincial Grand Master')
            ->assertDontSee('Watts')
            ->assertDontSee('1418')
            ->assertDontSee('Fraternus Amor Maneto');
    }

    public function test_the_officers_heading_follows_the_lodges_installation_month(): void
    {
        $this->assertSame('OFFICERS FOR 2026-2027', OfficersYear::label(CarbonImmutable::parse('2026-10-20'), 'October'));
        $this->assertSame('OFFICERS FOR 2025-2026', OfficersYear::label(CarbonImmutable::parse('2026-09-30'), 'October'));
        $this->assertSame('OFFICERS FOR 2026', OfficersYear::label(CarbonImmutable::parse('2026-06-01'), null));
    }
}
