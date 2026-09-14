<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\NewsletterType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterTypesTest extends TestCase
{
    use RefreshDatabase;

    private function createClub(): Club
    {
        $clubType = ClubType::firstOrCreate([
            'code' => 'masonic',
        ], [
            'name' => 'Masonic Lodge',
            'available_modules' => ['newsletters'],
            'default_settings' => [],
        ]);

        return Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Oxford Apollo Lodge',
            'slug' => 'oxford-apollo',
            'lodge_number' => '357',
            'province_region' => 'Oxfordshire',
            'town_city' => 'Oxford',
            'is_directory_listed' => true,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_view_and_create_newsletter_channels(): void
    {
        $club = $this->createClub();
        $admin = User::factory()->create();
        $club->users()->attach($admin->id, ['role' => 'admin']);

        // Access index page to trigger default channels seeding
        $response = $this->actingAs($admin)->get(route('admin.newsletters.types', ['clubSlug' => $club->slug]));
        $response->assertStatus(200);
        $this->assertDatabaseHas('newsletter_types', ['club_id' => $club->id, 'slug' => 'meeting-summonses']);

        // Create custom channel
        $response = $this->actingAs($admin)->post(route('admin.newsletters.types.store', ['clubSlug' => $club->slug]), [
            'name' => 'Master Special Circulars',
            'description' => 'Special notices from the Worshipful Master',
            'color' => '#8b5cf6',
            'icon' => '👑',
            'is_external_subscribable' => true,
            'require_approval' => true,
            'is_mandatory' => false,
            'require_home_club_info' => true,
            'default_roles' => ['member'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('newsletter_types', [
            'club_id' => $club->id,
            'name' => 'Master Special Circulars',
            'require_approval' => true,
        ]);
    }

    public function test_visiting_brother_can_subscribe_via_directory(): void
    {
        $club = $this->createClub();
        $type = NewsletterType::create([
            'club_id' => $club->id,
            'name' => 'Meeting Summonses',
            'slug' => 'meeting-summonses',
            'is_external_subscribable' => true,
            'require_approval' => false,
        ]);

        $visitingBrother = User::factory()->create(['email' => 'visitor@otherlodge.org']);

        $response = $this->actingAs($visitingBrother)->post(route('directory.subscribe', [
            'clubSlug' => $club->slug,
            'typeId' => $type->id,
        ]), [
            'home_club_name' => 'Churchill Lodge',
            'home_club_number' => '478',
            'rank' => 'W.Bro',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('newsletter_subscriptions', [
            'club_id' => $club->id,
            'newsletter_type_id' => $type->id,
            'email' => 'visitor@otherlodge.org',
            'status' => 'active',
            'home_club_name' => 'Churchill Lodge',
        ]);
    }

    public function test_member_portal_subscriptions_page(): void
    {
        $club = $this->createClub();
        $member = User::factory()->create();
        $club->users()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($member)->get(route('portal.subscriptions'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Portal/Subscriptions')
            ->has('clubMatrix')
        );
    }
}
