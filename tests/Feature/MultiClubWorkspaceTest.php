<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiClubWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_belong_to_multiple_clubs_with_different_roles(): void
    {
        $clubType = ClubType::create([
            'name' => 'Water Sports',
            'code' => 'rowing',
            'available_modules' => ['memberships'],
            'default_settings' => [],
        ]);

        $club1 = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Oxford Boat Club',
            'slug' => 'oxford-boating',
            'status' => 'active',
        ]);

        $club2 = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Bath RFC',
            'slug' => 'bath-rfc',
            'status' => 'active',
        ]);

        $user = User::factory()->create();

        // Attach as Admin in Oxford, Member in Bath
        $club1->users()->attach($user->id, ['role' => 'admin', 'member_number' => 'OUBC-001', 'status' => 'active']);
        $club2->users()->attach($user->id, ['role' => 'member', 'member_number' => 'BATH-412', 'status' => 'active']);

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('auth.clubs', 2)
            ->where('auth.clubs.0.role', 'admin')
            ->where('auth.clubs.1.role', 'member')
        );
    }

    public function test_can_render_dedicated_admin_clubs_index_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.clubs.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Clubs/Index')
            ->has('clubs')
        );
    }

    public function test_can_render_tenant_billing_page(): void
    {
        $clubType = ClubType::create([
            'name' => 'Water Sports',
            'code' => 'rowing',
            'available_modules' => ['memberships'],
            'default_settings' => [],
        ]);

        $club = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Oxford Boat Club',
            'slug' => 'oxford-boating',
            'status' => 'active',
        ]);

        $user = User::factory()->create();
        $club->users()->attach($user->id, ['role' => 'admin', 'member_number' => 'OUBC-001', 'status' => 'active']);

        $response = $this->actingAs($user)->get(route('billing.index', ['clubSlug' => $club->slug]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Billing/Index')
            ->has('club')
            ->has('activeProvider')
            ->has('plans')
        );
    }
}
