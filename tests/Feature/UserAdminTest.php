<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'name' => 'Rowing',
            'code' => 'rowing',
            'available_modules' => ['website_builder', 'memberships'],
            'default_settings' => [],
        ]);

        $this->club = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Oxford University Boat Club',
            'slug' => 'oxford-boating',
            'status' => 'active',
        ]);

        $this->adminUser = User::factory()->create();
        $this->club->users()->attach($this->adminUser->id, [
            'role' => 'admin',
            'member_number' => 'OUBC-001',
            'status' => 'active',
        ]);
    }

    public function test_can_display_user_roster_page(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.users.index', ['clubSlug' => $this->club->slug]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Index')
            ->has('members', 1)
        );
    }

    public function test_can_add_new_member_to_roster(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.users.store', ['clubSlug' => $this->club->slug]), [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'role' => 'coach',
                'member_number' => 'OUBC-055',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
        $this->assertDatabaseHas('club_user', [
            'club_id' => $this->club->id,
            'role' => 'coach',
            'member_number' => 'OUBC-055',
        ]);
    }

    public function test_can_update_member_role(): void
    {
        $member = User::factory()->create();
        $this->club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.users.role.update', [
                'clubSlug' => $this->club->slug,
                'userId' => $member->id,
            ]), [
                'role' => 'treasurer',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('club_user', [
            'club_id' => $this->club->id,
            'user_id' => $member->id,
            'role' => 'treasurer',
        ]);
    }

    public function test_can_remove_member_from_roster(): void
    {
        $member = User::factory()->create();
        $this->club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('admin.users.destroy', [
                'clubSlug' => $this->club->slug,
                'userId' => $member->id,
            ]));

        $response->assertRedirect();

        $this->assertDatabaseMissing('club_user', [
            'club_id' => $this->club->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_can_display_member_details_profile_page(): void
    {
        $member = User::factory()->create(['name' => 'Sarah Connor', 'email' => 'sarah@example.com']);
        $this->club->users()->attach($member->id, ['role' => 'member', 'member_number' => 'OUBC-777', 'status' => 'active']);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.users.show', [
                'clubSlug' => $this->club->slug,
                'userId' => $member->id,
            ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Show')
            ->where('member.name', 'Sarah Connor')
            ->where('member.member_number', 'OUBC-777')
        );
    }
}
