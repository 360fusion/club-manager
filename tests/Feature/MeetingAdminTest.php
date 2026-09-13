<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Meeting;
use App\Models\User;
use App\Services\MeetingScheduleService;
use App\Services\RsvpTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MeetingAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_nth_weekday_calculation_algorithm()
    {
        $service = new MeetingScheduleService();

        // 3rd Tuesday of October 2026 -> Oct 1, 2026 is Thursday. 1st Tue is Oct 6. 2nd Tue is Oct 13. 3rd Tue is Oct 20.
        $date = $service->calculateNthWeekday(2026, 10, '3rd', 'Tuesday');
        $this->assertEquals('2026-10-20', $date->format('Y-m-d'));

        // Last Friday of May 2026 -> May 31, 2026 is Sunday. Last Friday is May 29.
        $lastFriday = $service->calculateNthWeekday(2026, 5, 'last', 'Friday');
        $this->assertEquals('2026-05-29', $lastFriday->format('Y-m-d'));
    }

    public function test_rsvp_token_generation_and_validation()
    {
        $clubType = \App\Models\ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'masonic',
            'available_modules' => ['meetings'],
            'default_settings' => [],
        ]);

        $club = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Apollo Lodge No. 357',
            'slug' => 'oxford-lodge',
            'status' => 'active',
        ]);
        $user = User::factory()->create();
        $meeting = Meeting::create([
            'club_id' => $club->id,
            'title' => 'Regular Meeting No. 452',
            'meeting_date' => '2026-10-20',
            'starts_at' => '18:30',
            'venue' => 'Masonic Hall',
            'dress_code' => 'Dark Suit',
            'rsvp_cutoff_at' => Carbon::now()->addDays(10),
        ]);

        $tokenService = new RsvpTokenService();
        $rawToken = $tokenService->createTokenForUser($meeting, $user, Carbon::now()->addDays(5));

        $this->assertNotEmpty($rawToken);

        $validatedRsvp = $tokenService->validateToken($rawToken);
        $this->assertNotNull($validatedRsvp);
        $this->assertEquals($meeting->id, $validatedRsvp->meeting_id);
        $this->assertEquals($user->id, $validatedRsvp->user_id);
    }

    public function test_passwordless_rsvp_submission()
    {
        $clubType = \App\Models\ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'masonic',
            'available_modules' => ['meetings'],
            'default_settings' => [],
        ]);

        $club = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Apollo Lodge No. 357',
            'slug' => 'oxford-lodge',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['name' => 'John Doe']);
        $meeting = Meeting::create([
            'club_id' => $club->id,
            'title' => 'Regular Meeting No. 452',
            'meeting_date' => '2026-10-20',
            'starts_at' => '18:30',
            'venue' => 'Masonic Hall',
            'dress_code' => 'Dark Suit',
            'dining_cost_member' => 35.00,
            'dining_cost_guest' => 35.00,
            'payment_reference_prefix' => 'SUMMONS',
            'rsvp_cutoff_at' => Carbon::now()->addDays(10),
        ]);

        $tokenService = new RsvpTokenService();
        $rawToken = $tokenService->createTokenForUser($meeting, $user, Carbon::now()->addDays(5));

        $response = $this->post(route('summons.rsvp.store', ['token' => $rawToken]), [
            'attendance_status' => 'attending_dining',
            'dietary_requirements' => 'Vegetarian',
            'guests' => [
                [
                    'guest_name' => 'Bro. Mark Smith',
                    'home_club_lodge' => 'Apollo Lodge',
                    'attending_dining' => true,
                ]
            ]
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('meeting_rsvps', [
            'meeting_id' => $meeting->id,
            'user_id' => $user->id,
            'attendance_status' => 'attending_dining',
            'dietary_requirements' => 'Vegetarian',
        ]);
        $this->assertDatabaseHas('meeting_rsvp_guests', [
            'guest_name' => 'Bro. Mark Smith',
        ]);
    }

    public function test_generate_season_titles_use_date_format_without_meeting_number()
    {
        $clubType = \App\Models\ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'masonic',
            'available_modules' => ['meetings'],
            'default_settings' => [],
        ]);

        $club = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Apollo Lodge No. 357',
            'slug' => 'oxford-lodge-season',
            'status' => 'active',
        ]);

        $adminUser = User::factory()->create();
        $this->actingAs($adminUser);

        $response = $this->post(route('admin.meetings.generate_season', ['clubSlug' => $club->slug]), [
            'year' => 2026,
            'occurrence' => '3rd',
            'day_of_week' => 'Tuesday',
            'active_months' => [10],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('meetings', [
            'club_id' => $club->id,
            'title' => 'Meeting - 20th October 2026',
            'meeting_number' => null,
        ]);
    }

    public function test_admin_can_view_and_download_summons_pdf()
    {
        $clubType = \App\Models\ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'masonic',
            'available_modules' => ['meetings'],
            'default_settings' => [],
        ]);

        $club = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Apollo Lodge No. 357',
            'slug' => 'oxford-lodge-pdf',
            'status' => 'active',
        ]);

        $adminUser = User::factory()->create();
        $this->actingAs($adminUser);

        $meeting = Meeting::create([
            'club_id' => $club->id,
            'title' => 'Meeting - 20th October 2026',
            'meeting_date' => '2026-10-20',
            'starts_at' => '18:30',
            'venue' => 'Masonic Hall',
            'dress_code' => 'Dark Suit',
            'rsvp_cutoff_at' => Carbon::now()->addDays(10),
        ]);

        $viewResponse = $this->get(route('admin.meetings.pdf', ['clubSlug' => $club->slug, 'id' => $meeting->id]));
        $viewResponse->assertOk();

        $downloadResponse = $this->get(route('admin.meetings.pdf', ['clubSlug' => $club->slug, 'id' => $meeting->id, 'download' => 1]));
        $downloadResponse->assertOk();
    }

    public function test_admin_can_duplicate_meeting_summons_without_rsvp_stats()
    {
        $clubType = \App\Models\ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'masonic',
            'available_modules' => ['meetings'],
            'default_settings' => [],
        ]);

        $club = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Apollo Lodge No. 357',
            'slug' => 'oxford-lodge-duplicate',
            'status' => 'active',
        ]);

        $adminUser = User::factory()->create();
        $this->actingAs($adminUser);

        $meeting = Meeting::create([
            'club_id' => $club->id,
            'title' => 'Meeting - 20th October 2026',
            'meeting_date' => '2026-10-20',
            'starts_at' => '18:30',
            'venue' => 'Masonic Hall, Oxford',
            'dress_code' => 'Dark Suit, Craft Regalia',
            'status' => 'published',
            'rsvp_cutoff_at' => Carbon::now()->addDays(10),
        ]);

        $meeting->agendaItems()->create([
            'item_number' => 1,
            'title' => 'To confirm minutes of previous meeting.',
        ]);

        $response = $this->post(route('admin.meetings.duplicate', ['clubSlug' => $club->slug, 'id' => $meeting->id]));

        $response->assertSessionHasNoErrors();

        $duplicated = Meeting::where('club_id', $club->id)->where('id', '!=', $meeting->id)->first();
        $this->assertNotNull($duplicated);
        $this->assertEquals('draft', $duplicated->status);
        $this->assertEquals('Masonic Hall, Oxford', $duplicated->venue);
        $this->assertEquals(1, $duplicated->agendaItems->count());
        $this->assertEquals(0, \App\Models\MeetingRsvp::where('meeting_id', $duplicated->id)->count());
    }

    public function test_admin_can_update_rsvp_payment_status()
    {
        $clubType = \App\Models\ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'masonic',
            'available_modules' => ['meetings'],
            'default_settings' => [],
        ]);

        $club = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Apollo Lodge No. 357',
            'slug' => 'oxford-lodge-payment-test',
            'status' => 'active',
        ]);

        $adminUser = User::factory()->create();
        $memberUser = User::factory()->create();
        $club->users()->attach($adminUser->id, ['role' => 'admin']);
        $club->users()->attach($memberUser->id, ['role' => 'member']);

        $this->actingAs($adminUser);

        $meeting = Meeting::create([
            'club_id' => $club->id,
            'title' => 'Regular Meeting',
            'meeting_date' => '2026-11-20',
            'starts_at' => '18:30',
            'venue' => 'Masonic Hall, Oxford',
            'dress_code' => 'Dark Suit',
            'status' => 'published',
            'rsvp_cutoff_at' => Carbon::now()->addDays(10),
        ]);

        $response = $this->post(route('admin.meetings.rsvp.payment_status', ['clubSlug' => $club->slug, 'id' => $meeting->id]), [
            'user_id' => $memberUser->id,
            'payment_status' => 'paid',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('meeting_rsvps', [
            'meeting_id' => $meeting->id,
            'user_id' => $memberUser->id,
            'payment_status' => 'paid',
        ]);
    }
}
