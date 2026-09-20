<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Meeting;
use App\Models\User;
use App\Services\RsvpTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VisitorRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_self_register_for_a_club()
    {
        $clubType = ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'masonic',
            'available_modules' => ['meetings'],
            'default_settings' => [],
        ]);

        $club = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Oxford Lodge No. 1418',
            'slug' => 'oxford-lodge-visitors',
            'status' => 'active',
        ]);

        $response = $this->post(route('clubs.visitor.store', ['slug' => $club->slug]), [
            'name' => 'WBro Arthur Pendelton',
            'email' => 'arthur.visitor@example.com',
            'rank' => 'WBro',
            'home_club_name' => 'Apollo Lodge',
            'home_club_number' => '357',
            'phone' => '07700 900888',
            'dietary_notes' => 'Vegetarian',
        ]);

        $response->assertSessionHasNoErrors();

        $user = User::where('email', 'arthur.visitor@example.com')->first();
        $this->assertNotNull($user);

        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role' => 'visitor',
            'home_club_name' => 'Apollo Lodge',
            'home_club_number' => '357',
        ]);
    }

    public function test_visitor_rsvp_omits_apologies_option()
    {
        $clubType = ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'masonic',
            'available_modules' => ['meetings'],
            'default_settings' => [],
        ]);

        $club = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Oxford Lodge No. 1418',
            'slug' => 'oxford-lodge-rsvp-visitor',
            'status' => 'active',
        ]);

        $visitorUser = User::factory()->create(['name' => 'Visitor John']);
        $club->users()->attach($visitorUser->id, [
            'role' => 'visitor',
            'home_club_name' => 'Friendship Lodge',
            'home_club_number' => '100',
        ]);

        $meeting = Meeting::create([
            'club_id' => $club->id,
            'title' => 'Meeting - 20th October 2026',
            'meeting_date' => '2026-10-20',
            'starts_at' => '18:30',
            'venue' => 'Masonic Hall',
            'dress_code' => 'Dark Suit',
            'rsvp_cutoff_at' => Carbon::now()->addDays(10),
        ]);

        $tokenService = new RsvpTokenService;
        $rawToken = $tokenService->createTokenForUser($meeting, $visitorUser, Carbon::now()->addDays(5));

        // Attempting to submit 'apologies' as a visitor should be rejected by validation
        $invalidResponse = $this->post(route('summons.rsvp.store', ['token' => $rawToken]), [
            'attendance_status' => 'apologies',
        ]);
        $invalidResponse->assertSessionHasErrors(['attendance_status']);

        // Submitting 'attending_dining' should succeed
        $validResponse = $this->post(route('summons.rsvp.store', ['token' => $rawToken]), [
            'attendance_status' => 'attending_dining',
            'dietary_requirements' => 'Gluten Free',
        ]);
        $validResponse->assertSessionHasNoErrors();

        $this->assertDatabaseHas('meeting_rsvps', [
            'meeting_id' => $meeting->id,
            'user_id' => $visitorUser->id,
            'attendance_status' => 'attending_dining',
            'dietary_requirements' => 'Gluten Free',
        ]);
    }
}
