<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\MeetingWalkIn;
use App\Models\User;
use App\Services\Meetings\MeetingReplies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingSigningInTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $secretary;

    private User $tom;

    private User $ann;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create();
        $this->secretary = $this->person('Sam Secretary', 'coach');
        $this->tom = $this->person('Tom Hart');
        $this->ann = $this->person('Ann Lee');
        Member::create(['club_id' => $this->club->id, 'user_id' => $this->ann->id, 'first_name' => 'Ann', 'last_name' => 'Lee', 'masonic_rank' => 'WBro', 'membership_status' => 'active']);
        $this->person('Vic Visitor', 'visitor');
    }

    private function person(string $name, string $role = 'member'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $this->club->users()->attach($user->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    private function meeting(string $date): Meeting
    {
        return Meeting::create(['club_id' => $this->club->id, 'title' => "Meeting {$date}", 'meeting_date' => $date, 'starts_at' => '18:30:00', 'venue' => 'Hall', 'dress_code' => 'Suit', 'status' => 'published']);
    }

    private function signIn(Meeting $meeting, string $kind, int $id, bool $present = true)
    {
        return $this->actingAs($this->secretary)->post(route('admin.meetings.signing_in.update', ['clubSlug' => $this->club->slug, 'id' => $meeting->id]), ['kind' => $kind, 'id' => $id, 'present' => $present]);
    }

    public function test_members_their_guests_and_visitors_at_the_door_are_signed_in_and_counted(): void
    {
        $meeting = $this->meeting(now()->toDateString());
        $toms = app(MeetingReplies::class)->rsvpFor($meeting, $this->tom);
        $toms->update(['attendance_status' => 'attending_dining']);
        $gus = $toms->guests()->create(['guest_name' => 'Gus Guest', 'home_club_lodge' => 'Unity No 5', 'attending_dining' => true]);
        $page = route('admin.meetings.signing_in', ['clubSlug' => $this->club->slug, 'id' => $meeting->id]);

        // Every member is listed whatever they replied (Ann never did), with their rank; a visitor who has not said they are coming is not.
        $this->actingAs($this->secretary)->get($page)->assertInertia(fn ($inertia) => $inertia
            ->component('Admin/Meetings/SigningIn')
            ->where('book.members', fn ($members) => collect($members)->pluck('name')->sort()->values()->all() === ['Sam Secretary', 'Tom Hart', 'WBro Ann Lee'])
            ->where('book.guests.0.name', 'Gus Guest'));

        $this->signIn($meeting, 'member', $this->tom->id)->assertSessionHasNoErrors();
        $this->signIn($meeting, 'member', $this->ann->id)->assertSessionHasNoErrors();
        $this->signIn($meeting, 'guest', $gus->id)->assertSessionHasNoErrors();
        $this->actingAs($this->secretary)->post(route('admin.meetings.signing_in.visitors.store', ['clubSlug' => $this->club->slug, 'id' => $meeting->id]), ['name' => 'John Brown', 'rank' => 'WBro', 'lodge' => 'St John No 1234'])->assertSessionHasNoErrors();

        $this->assertNotNull(MeetingRsvp::where('meeting_id', $meeting->id)->where('user_id', $this->ann->id)->sole()->attended_at);
        $this->actingAs($this->secretary)->get($page)->assertInertia(fn ($inertia) => $inertia
            ->where('book.counts', ['members' => 2, 'guests' => 1, 'visitors' => 1, 'total' => 4])
            ->where('book.walk_ins.0.name', 'WBro John Brown'));

        // A tap in error is undone, and a walk-in can be taken off.
        $this->signIn($meeting, 'member', $this->ann->id, false);
        $this->actingAs($this->secretary)->delete(route('admin.meetings.signing_in.visitors.destroy', ['clubSlug' => $this->club->slug, 'id' => $meeting->id, 'walkIn' => MeetingWalkIn::sole()->id]));
        $this->actingAs($this->secretary)->get($page)->assertInertia(fn ($inertia) => $inertia->where('book.counts.total', 2));
    }

    public function test_only_this_lodges_people_can_be_signed_in(): void
    {
        $meeting = $this->meeting(now()->toDateString());
        $other = Club::factory()->create();
        $stranger = User::factory()->create();
        $other->users()->attach($stranger->id, ['role' => 'member', 'status' => 'active']);
        $elsewhere = Meeting::create(['club_id' => $other->id, 'title' => 'Other', 'meeting_date' => now()->toDateString(), 'starts_at' => '18:30:00', 'venue' => 'Hall', 'dress_code' => 'Suit', 'status' => 'published']);
        $theirGuest = app(MeetingReplies::class)->rsvpFor($elsewhere, $stranger)->guests()->create(['guest_name' => 'Not Ours', 'attending_dining' => false]);

        $this->signIn($meeting, 'member', $stranger->id)->assertNotFound();
        $this->signIn($meeting, 'guest', $theirGuest->id)->assertNotFound();
        $this->assertNull($theirGuest->fresh()->attended_at);
    }

    public function test_attendance_counts_only_meetings_that_used_the_book_and_lists_who_has_not_been_seen(): void
    {
        $unused = $this->meeting(now()->subMonths(5)->toDateString());
        [$first, $second, $latest] = [$this->meeting(now()->subMonths(3)->toDateString()), $this->meeting(now()->subMonths(2)->toDateString()), $this->meeting(now()->subMonth()->toDateString())];

        foreach ([$first, $second, $latest] as $meeting) {
            $this->signIn($meeting, 'member', $this->tom->id);
        }
        $this->signIn($unused, 'member', $this->ann->id, false);

        $this->actingAs($this->secretary)->get(route('admin.meetings.attendance', ['clubSlug' => $this->club->slug]))->assertInertia(fn ($inertia) => $inertia
            ->component('Admin/Meetings/Attendance')
            ->where('attendance.meetings', 3)
            ->where('attendance.members', function ($members) {
                $byName = collect($members)->keyBy('name');

                return $byName['Tom Hart']['attended'] === 3 && $byName['Tom Hart']['missed_in_a_row'] === 0
                    && $byName['WBro Ann Lee']['attended'] === 0 && $byName['WBro Ann Lee']['missed_in_a_row'] === 3
                    && ! $byName->has('Vic Visitor');
            }));
    }
}
