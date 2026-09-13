<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\EventTicketTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventSubscribersTest extends TestCase
{
    use RefreshDatabase;

    private function createClub(): Club
    {
        $clubType = ClubType::firstOrCreate([
            'code' => 'masonic',
        ], [
            'name' => 'Masonic Lodge',
            'available_modules' => ['events'],
            'default_settings' => [],
        ]);

        return Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Oxford Lodge',
            'slug' => 'oxford-lodge',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_view_event_subscribers_page(): void
    {
        $club = $this->createClub();
        $admin = User::factory()->create();
        $club->users()->attach($admin->id, [
            'role' => 'admin',
        ]);

        $attendee = User::factory()->create([
            'name' => 'John Subscriber',
            'email' => 'john@example.com',
        ]);
        $club->users()->attach($attendee->id, [
            'role' => 'member',
            'home_club_name' => 'Apollo Lodge No. 357',
            'rank' => 'W.Bro',
        ]);

        $event = Event::create([
            'club_id' => $club->id,
            'title' => 'Gala Dinner 2026',
            'slug' => 'gala-dinner-2026',
            'starts_at' => '2026-12-01 19:00:00',
            'status' => 'upcoming',
        ]);

        $tier = EventTicketTier::create([
            'event_id' => $event->id,
            'name' => 'Standard Ticket',
            'price' => 45.00,
        ]);

        DB::table('event_user')->insert([
            'event_id' => $event->id,
            'user_id' => $attendee->id,
            'ticket_tier_id' => $tier->id,
            'payment_status' => 'paid',
            'attending_dining' => true,
            'menu_selections' => json_encode(['starter' => 'Soup', 'main' => 'Roast Beef', 'dessert' => 'Cheesecake']),
            'dietary_requirements' => 'Nut allergy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.events.subscribers', ['clubSlug' => $club->slug, 'id' => $event->id]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Events/Subscribers')
            ->where('event.title', 'Gala Dinner 2026')
            ->has('subscribers', 1)
            ->where('subscribers.0.name', 'John Subscriber')
            ->where('subscribers.0.home_club_lodge', 'Apollo Lodge No. 357')
            ->where('subscribers.0.payment_status', 'paid')
            ->where('subscribers.0.menu_selections.starter', 'Soup')
            ->where('subscribers.0.dietary_requirements', 'Nut allergy')
        );
    }

    public function test_admin_can_update_subscriber_payment_status(): void
    {
        $club = $this->createClub();
        $admin = User::factory()->create();
        $club->users()->attach($admin->id, [
            'role' => 'admin',
        ]);

        $attendee = User::factory()->create();
        $event = Event::create([
            'club_id' => $club->id,
            'title' => 'Annual Banquet',
            'slug' => 'annual-banquet',
            'starts_at' => '2026-11-20 18:30:00',
            'status' => 'upcoming',
        ]);

        $tier = EventTicketTier::create([
            'event_id' => $event->id,
            'name' => 'Standard Ticket',
            'price' => 30.00,
        ]);

        DB::table('event_user')->insert([
            'event_id' => $event->id,
            'user_id' => $attendee->id,
            'ticket_tier_id' => $tier->id,
            'payment_status' => 'unpaid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.events.subscribers.payment_status', [
                'clubSlug' => $club->slug,
                'id' => $event->id,
                'userId' => $attendee->id,
            ]), [
                'payment_status' => 'paid',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('event_user', [
            'event_id' => $event->id,
            'user_id' => $attendee->id,
            'payment_status' => 'paid',
        ]);
    }
}
