<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_display_member_portal_page(): void
    {
        $clubType = ClubType::create([
            'name' => 'Rowing',
            'code' => 'rowing',
            'available_modules' => ['memberships', 'events'],
            'default_settings' => [],
        ]);

        $club = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Oxford University Boat Club',
            'slug' => 'oxford-boating',
            'status' => 'active',
        ]);

        $user = User::factory()->create();
        $club->users()->attach($user->id, [
            'role' => 'member',
            'member_number' => 'OUBC-142',
            'status' => 'active',
        ]);

        $event = Event::create([
            'club_id' => $club->id,
            'title' => 'Weekly Outing',
            'slug' => 'weekly-outing',
            'starts_at' => now()->addDays(2),
            'status' => 'upcoming',
        ]);

        $response = $this->actingAs($user)->get(route('member.dashboard', ['slug' => $club->slug]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Member/Dashboard')
            ->has('club')
            ->has('events')
            ->has('newsletters')
        );
    }

    public function test_can_display_member_clubs_page(): void
    {
        $clubType = ClubType::create([
            'name' => 'Rowing',
            'code' => 'rowing',
            'available_modules' => ['memberships', 'events'],
            'default_settings' => [],
        ]);

        $club = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Oxford University Boat Club',
            'slug' => 'oxford-boating',
            'status' => 'active',
        ]);

        $user = User::factory()->create();
        $club->users()->attach($user->id, ['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($user)->get(route('member.clubs', ['slug' => $club->slug]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Member/Clubs')
            ->has('clubs')
        );
    }

    public function test_can_display_member_events_page(): void
    {
        $clubType = ClubType::create([
            'name' => 'Rowing',
            'code' => 'rowing',
            'available_modules' => ['memberships', 'events'],
            'default_settings' => [],
        ]);

        $club = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Oxford University Boat Club',
            'slug' => 'oxford-boating',
            'status' => 'active',
        ]);

        $user = User::factory()->create();
        $club->users()->attach($user->id, ['role' => 'member', 'status' => 'active']);

        $response = $this->actingAs($user)->get(route('member.events', ['slug' => $club->slug]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Member/Events')
            ->has('events')
        );
    }

    public function test_can_display_member_dues_page(): void
    {
        $clubType = ClubType::create([
            'name' => 'Rowing',
            'code' => 'rowing',
            'available_modules' => ['memberships', 'events'],
            'default_settings' => [],
        ]);

        $club = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Oxford University Boat Club',
            'slug' => 'oxford-boating',
            'status' => 'active',
        ]);

        $user = User::factory()->create();
        $club->users()->attach($user->id, ['role' => 'member', 'status' => 'active']);

        $response = $this->actingAs($user)->get(route('member.dues', ['slug' => $club->slug]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Member/Dues')
            ->has('plans')
            ->has('invoices')
        );
    }

    public function test_can_display_member_profile_page(): void
    {
        $clubType = ClubType::create([
            'name' => 'Rowing',
            'code' => 'rowing',
            'available_modules' => ['memberships', 'events'],
            'default_settings' => [],
        ]);

        $club = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Oxford University Boat Club',
            'slug' => 'oxford-boating',
            'status' => 'active',
        ]);

        $user = User::factory()->create();
        $club->users()->attach($user->id, ['role' => 'member', 'status' => 'active']);

        $response = $this->actingAs($user)->get(route('member.profile', ['slug' => $club->slug]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Member/Profile')
            ->has('user')
        );
    }
}
