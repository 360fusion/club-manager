<?php

namespace Tests\Feature\Launchpad;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Launchpad\Models\WorkspaceEvent;
use Tests\TestCase;

/** Meeting changes go in the club's activity log (and to its webhooks), once each */
class ClubMeetingEventsTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
    }

    public function test_a_meeting_is_recorded_when_made_changed_and_cancelled(): void
    {
        $meeting = Meeting::create(['club_id' => $this->club->id, 'title' => 'Installation', 'meeting_date' => '2026-12-12', 'starts_at' => '18:30', 'venue' => 'Hall', 'dress_code' => 'Suit']);
        $this->assertSame(['meeting.created'], $this->lines());
        $this->assertStringContainsString('Installation', WorkspaceEvent::withoutGlobalScopes()->sole()->summary);

        $meeting->update(['venue' => 'Town Hall']);
        $meeting->update(['festive_board_menu' => 'Roast']);
        $this->assertSame(['meeting.created', 'meeting.updated'], $this->lines());

        $meeting->update(['status' => 'cancelled', 'venue' => 'Nowhere']);
        $this->assertSame(['meeting.created', 'meeting.updated', 'meeting.cancelled'], $this->lines());
    }

    /** A summons makes a pending row for each member: that is not a reply */
    public function test_replies_are_recorded_and_summons_rows_and_payment_changes_are_not(): void
    {
        $meeting = Meeting::create(['club_id' => $this->club->id, 'title' => 'Regular', 'meeting_date' => '2026-12-12', 'starts_at' => '18:30', 'venue' => 'Hall', 'dress_code' => 'Suit']);
        $rsvp = MeetingRsvp::create(['meeting_id' => $meeting->id, 'user_id' => User::factory()->create(['name' => 'Ray Reply'])->id, 'token_hash' => 'a', 'token_expires_at' => now()->addWeek(), 'attendance_status' => 'pending']);

        $rsvp->update(['attendance_status' => 'apologies']);
        $rsvp->update(['payment_status' => 'paid']);
        MeetingRsvp::create(['meeting_id' => $meeting->id, 'user_id' => User::factory()->create()->id, 'token_hash' => 'b', 'token_expires_at' => now()->addWeek(), 'attendance_status' => 'attending_dining']);

        $this->assertSame(['meeting.created', 'meeting.rsvp', 'meeting.rsvp'], $this->lines());
        $this->assertStringContainsString('Ray Reply', WorkspaceEvent::withoutGlobalScopes()->where('action', 'meeting.rsvp')->orderBy('id')->first()->summary);
    }

    public function test_a_change_with_no_one_signed_in_is_by_the_system(): void
    {
        Meeting::create(['club_id' => $this->club->id, 'title' => 'Regular', 'meeting_date' => '2026-12-12', 'starts_at' => '18:30', 'venue' => 'Hall', 'dress_code' => 'Suit']);

        $this->assertSame('The system', WorkspaceEvent::withoutGlobalScopes()->sole()->actor_name);
    }

    /** @return list<string> */
    private function lines(): array
    {
        return WorkspaceEvent::withoutGlobalScopes()->where('workspace_id', $this->club->id)->orderBy('id')->pluck('action')->all();
    }
}
