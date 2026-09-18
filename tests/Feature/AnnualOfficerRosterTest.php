<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Services\AnnualOfficerRosterService;
use App\Models\Club;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AnnualOfficerRosterTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;
    private Member $member1;
    private Member $member2;
    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = \App\Models\ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'masonic',
            'available_modules' => ['meetings'],
            'default_settings' => [],
        ]);

        $this->club = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Test Lodge',
            'slug' => 'test-lodge',
            'status' => 'active',
        ]);

        $this->member1 = Member::create([
            'club_id' => $this->club->id,
            'first_name' => 'Arthur',
            'last_name' => 'Pendelton',
            'membership_status' => 'active',
            'current_office' => 'member',
        ]);

        $this->member2 = Member::create([
            'club_id' => $this->club->id,
            'first_name' => 'Charles',
            'last_name' => 'Darwin',
            'membership_status' => 'active',
            'current_office' => 'member',
        ]);

        $this->meeting = Meeting::create([
            'club_id' => $this->club->id,
            'title' => 'Installation & Election Meeting',
            'meeting_date' => '2026-10-15',
            'starts_at' => '18:30',
            'venue' => 'Masonic Hall',
            'dress_code' => 'Dark Suit',
            'status' => 'draft',
        ]);
    }

    public function test_prevents_assigning_multiple_progressive_offices_to_same_member(): void
    {
        $service = new AnnualOfficerRosterService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot hold more than one Progressive Office');

        $service->validateAssignments([
            ['member_id' => $this->member1->id, 'office' => 'sw'],
            ['member_id' => $this->member1->id, 'office' => 'jw'], // Second progressive office!
        ]);
    }

    public function test_allows_multiple_administrative_offices_to_same_member(): void
    {
        $service = new AnnualOfficerRosterService();

        // Bro Arthur is DC (Admin) AND Charity Steward (Admin) AND Almoner (Admin) -> Valid!
        $assignments = [
            ['member_id' => $this->member1->id, 'office' => 'dc'],
            ['member_id' => $this->member1->id, 'office' => 'charity_steward'],
            ['member_id' => $this->member1->id, 'office' => 'almoner'],
            ['member_id' => $this->member2->id, 'office' => 'sw'], // Progressive
        ];

        $service->validateAssignments($assignments);

        $roster = $service->saveRoster($this->club, '2026-2027', $assignments, $this->meeting->id);

        $this->assertCount(4, $roster->assignments);
        $this->assertEquals('2026-2027', $roster->masonic_year);
    }

    public function test_confirms_roster_and_syncs_member_profiles(): void
    {
        $service = new AnnualOfficerRosterService();

        $assignments = [
            ['member_id' => $this->member1->id, 'office' => 'dc'],
            ['member_id' => $this->member1->id, 'office' => 'charity_steward'],
            ['member_id' => $this->member2->id, 'office' => 'sw'],
        ];

        $roster = $service->saveRoster($this->club, '2026-2027', $assignments, $this->meeting->id);
        $confirmed = $service->confirmRoster($roster);

        $this->assertEquals('confirmed', $confirmed->status);
        $this->assertNotNull($confirmed->confirmed_at);

        $this->member1->refresh();
        $this->member2->refresh();

        $this->assertEquals(LodgeOffice::DirectorOfCeremonies, $this->member1->current_office);
        $this->assertEquals(LodgeOffice::SeniorWarden, $this->member2->current_office);

        // Check active_offices accessor for member1 (holding dual roles: DC & Charity Steward)
        $activeOffices = $this->member1->active_offices;
        $this->assertCount(2, $activeOffices);
        $this->assertContains(LodgeOffice::DirectorOfCeremonies, $activeOffices);
        $this->assertContains(LodgeOffice::CharitySteward, $activeOffices);
    }

    public function test_meeting_officer_election_endpoints(): void
    {
        $admin = User::factory()->create();
        $this->club->users()->attach($admin->id, ['role' => 'admin']);

        $response = $this->actingAs($admin)->getJson(
            route('admin.meetings.officer_election.data', ['clubSlug' => $this->club->slug, 'id' => $this->meeting->id])
        );

        $response->assertStatus(200)
            ->assertJsonStructure(['masonic_year', 'members', 'offices']);

        $postResponse = $this->actingAs($admin)->postJson(
            route('admin.meetings.officer_election.store', ['clubSlug' => $this->club->slug, 'id' => $this->meeting->id]),
            [
                'masonic_year' => '2026-2027',
                'assignments' => [
                    ['member_id' => $this->member1->id, 'office' => 'sw'],
                    ['member_id' => $this->member2->id, 'office' => 'charity_steward'],
                    ['member_id' => $this->member2->id, 'office' => 'almoner'],
                ],
            ]
        );

        $postResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $confirmResponse = $this->actingAs($admin)->postJson(
            route('admin.meetings.officer_election.confirm', ['clubSlug' => $this->club->slug, 'id' => $this->meeting->id]),
            ['masonic_year' => '2026-2027']
        );

        $confirmResponse->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_auto_installs_roster_after_installation_meeting_passes(): void
    {
        $service = new AnnualOfficerRosterService();

        // Installation meeting in October 2025 (in the past)
        $pastInstallationMeeting = Meeting::create([
            'club_id' => $this->club->id,
            'title' => 'Annual Installation Meeting 2025',
            'meeting_date' => '2025-10-15',
            'starts_at' => '18:00',
            'venue' => 'Masonic Hall',
            'dress_code' => 'Dinner Jacket',
            'status' => 'published',
        ]);

        $assignments = [
            ['member_id' => $this->member1->id, 'office' => 'wm'],
            ['member_id' => $this->member2->id, 'office' => 'sw'],
        ];

        $roster = $service->saveRoster($this->club, '2025-2026', $assignments, $pastInstallationMeeting->id, 'confirmed');

        $this->assertEquals('confirmed', $roster->status);

        // Run auto install check
        $service->checkAndAutoInstallPassedInstallationMeetings($this->club);

        $roster->refresh();
        $this->assertEquals('installed', $roster->status);

        $this->member1->refresh();
        $this->assertEquals(LodgeOffice::WorshipfulMaster, $this->member1->current_office);
    }
}
