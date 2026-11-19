<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\GrantApprovalStatus;
use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Models\CharityGrant;
use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;
use App\Models\Meeting;
use App\Models\User;
use App\Services\RsvpTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MemberSummonsTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $member;

    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create();
        $this->member = User::factory()->create();
        $this->club->users()->attach($this->member->id, ['role' => 'member', 'status' => 'active']);
        $this->meeting = $this->meeting(now()->addDays(9));
    }

    private function meeting(Carbon $date): Meeting
    {
        return Meeting::create(['club_id' => $this->club->id, 'title' => 'Regular', 'meeting_date' => $date->toDateString(), 'starts_at' => '19:00:00', 'venue' => 'Hall', 'dress_code' => 'Suit', 'status' => 'published']);
    }

    private function summons()
    {
        return $this->actingAs($this->member)->get(route('member.meetings.summons', ['slug' => $this->club->slug, 'id' => $this->meeting->id]));
    }

    public function test_replying_in_the_member_area_keeps_the_emailed_reply_link_working(): void
    {
        $token = app(RsvpTokenService::class)->createTokenForUser($this->meeting, $this->member, now()->addDays(30));

        $this->actingAs($this->member)->post(route('member.meetings.rsvp', ['slug' => $this->club->slug, 'id' => $this->meeting->id]), ['attendance_status' => 'apologies'])
            ->assertSessionHasNoErrors();

        $this->assertNotNull(app(RsvpTokenService::class)->validateToken($token));
    }

    public function test_the_summons_lists_only_this_meetings_grants_still_to_be_voted_on_without_bank_details(): void
    {
        $grant = fn (array $extra) => CharityGrant::create($extra + ['club_id' => $this->club->id, 'purpose' => 'Relief', 'amount' => 250, 'bacs_reference' => 'SECRET-REF', 'meeting_id' => $this->meeting->id, 'approval_status' => GrantApprovalStatus::Proposed]);
        $grant(['recipient_name' => 'Air Ambulance']);
        $grant(['recipient_name' => 'Already Paid', 'approval_status' => GrantApprovalStatus::Disbursed]);
        $grant(['recipient_name' => 'Other Meeting', 'meeting_id' => $this->meeting(now()->addDays(40))->id]);

        $this->summons()->assertInertia(fn ($page) => $page
            ->has('charityGrants', 1)
            ->where('charityGrants.0.recipient_name', 'Air Ambulance')
            ->missing('charityGrants.0.bacs_reference'));
    }

    public function test_the_summons_names_the_secretary_and_master_by_office_with_their_rank(): void
    {
        $office = fn (string $first, string $rank, LodgeOffice $holds) => Member::create(['club_id' => $this->club->id, 'first_name' => $first, 'last_name' => 'Smith', 'masonic_rank' => $rank, 'membership_status' => 'active', 'current_office' => $holds->value, 'email' => strtolower($first).'@example.test']);
        $secretary = $office('Alan', 'VWBro', LodgeOffice::Secretary);
        $master = $office('Brian', 'WBro', LodgeOffice::WorshipfulMaster);

        $this->summons()->assertInertia(fn ($page) => $page
            ->where('secretaryUser.name', $secretary->formatted_rank_name)
            ->where('secretaryUser.email', 'alan@example.test')
            ->where('worshipfulMaster.name', $master->formatted_rank_name));
        $this->assertStringStartsWith('VWBro', $secretary->formatted_rank_name);
    }

    public function test_dining_closes_at_the_cut_off_by_the_lodges_own_clock_in_summer(): void
    {
        $this->meeting->update(['meeting_date' => '2026-07-10', 'rsvp_cutoff_at' => '2026-07-05 23:59:59']);
        $reply = fn (string $status) => $this->actingAs($this->member)->post(route('member.meetings.rsvp', ['slug' => $this->club->slug, 'id' => $this->meeting->id]), ['attendance_status' => $status]);

        // 22:30 UTC is 23:30 in London: dining is still open.
        $this->travelTo(Carbon::parse('2026-07-05 22:30:00', 'UTC'));
        $reply('attending_dining')->assertSessionHasNoErrors();
        $reply('apologies')->assertSessionHasNoErrors();

        // 23:30 UTC is already 00:30 the next day in London: dining has closed, although it is not yet midnight UTC.
        $this->travelTo(Carbon::parse('2026-07-05 23:30:00', 'UTC'));
        $reply('attending_dining')->assertSessionHasErrors('cutoff');
        $reply('attending_meeting_only')->assertSessionHasNoErrors();
    }
}
