<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Club;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemberCalendarStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_calendar_marks_dining_and_bookings_still_to_pay(): void
    {
        $club = Club::factory()->create();
        $member = User::factory()->create();
        $club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        // Meetings are one per lodge per day, so each goes on its own day of next month.
        $day = now()->addMonth()->startOfMonth();

        $meeting = fn (string $title, float $cost) => Meeting::create(['club_id' => $club->id, 'title' => $title, 'meeting_date' => $day->addDay()->toDateString(), 'starts_at' => '18:30:00', 'venue' => 'Masonic Hall', 'dress_code' => 'Dark suit', 'status' => 'published', 'dining_cost_member' => $cost]);
        $event = fn (string $title) => Event::create(['club_id' => $club->id, 'title' => $title, 'slug' => str($title)->slug(), 'starts_at' => $day->copy()->setTime(19, 0), 'status' => 'upcoming', 'visibility' => Visibility::Club, 'rsvp_audience' => Visibility::Club]);

        MeetingRsvp::create(['meeting_id' => $meeting('Dining unpaid', 30)->id, 'user_id' => $member->id, 'token_hash' => uniqid(), 'token_expires_at' => now()->addWeek(), 'attendance_status' => 'attending_dining']);
        MeetingRsvp::create(['meeting_id' => $meeting('Dining paid', 30)->id, 'user_id' => $member->id, 'token_hash' => uniqid(), 'token_expires_at' => now()->addWeek(), 'attendance_status' => 'attending_dining', 'payment_status' => 'paid']);
        MeetingRsvp::create(['meeting_id' => $meeting('Free dining', 0)->id, 'user_id' => $member->id, 'token_hash' => uniqid(), 'token_expires_at' => now()->addWeek(), 'attendance_status' => 'attending_dining']);
        $meeting('Not replied', 30);

        EventRegistration::create(['event_id' => $event('Part paid dinner')->id, 'user_id' => $member->id, 'contact_name' => 'Member', 'status' => 'attending', 'total' => 70, 'amount_paid' => 20]);
        EventRegistration::create(['event_id' => $event('Paid dinner')->id, 'user_id' => $member->id, 'contact_name' => 'Member', 'status' => 'attending', 'total' => 70, 'amount_paid' => 70]);
        EventRegistration::create(['event_id' => $event('Declined dinner')->id, 'user_id' => $member->id, 'contact_name' => 'Member', 'status' => 'declined', 'total' => 70, 'amount_paid' => 0]);

        $this->actingAs($member)->get('/members/calendar?month='.$day->format('Y-m'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('items', fn ($items) => collect($items)->filter(fn ($item) => $item['unpaid'])->pluck('title')->sort()->values()->all() === ['Dining unpaid', 'Part paid dinner']));
    }

    public function test_a_place_the_member_says_they_paid_by_bank_is_marked_as_payment_sent(): void
    {
        $club = Club::factory()->create();
        $member = User::factory()->create();
        $club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $day = now()->addMonth()->startOfMonth()->addDay();
        $meeting = Meeting::create(['club_id' => $club->id, 'title' => 'Dining sent', 'meeting_date' => $day->toDateString(), 'starts_at' => '18:30:00', 'venue' => 'Masonic Hall', 'dress_code' => 'Dark suit', 'status' => 'published', 'dining_cost_member' => 30]);
        MeetingRsvp::create(['meeting_id' => $meeting->id, 'user_id' => $member->id, 'token_hash' => uniqid(), 'token_expires_at' => now()->addWeek(), 'attendance_status' => 'attending_dining'])->forceFill(['said_paid_at' => now()])->save();

        $this->actingAs($member)->get('/members/calendar?month='.$day->format('Y-m'))
            ->assertInertia(fn (Assert $page) => $page->where('items', fn ($items) => collect($items)->firstWhere('title', 'Dining sent')['said_paid'] === true));
        $this->actingAs($member)->get(route('members.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('upNext', fn ($items) => collect($items)->firstWhere('title', 'Dining sent')['said_paid'] === true));
    }
}
