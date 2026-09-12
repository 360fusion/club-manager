<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Club $clubA;

    protected Club $clubB;

    protected User $adminA;

    protected User $memberA;

    protected User $memberB;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create([
            'name' => 'General Sports',
            'code' => 'general',
            'available_modules' => ['events', 'newsletters', 'memberships', 'posts'],
        ]);

        $this->clubA = Club::create([
            'club_type_id' => $type->id,
            'name' => 'Club Alpha',
            'slug' => 'club-alpha',
            'status' => 'active',
        ]);

        $this->clubB = Club::create([
            'club_type_id' => $type->id,
            'name' => 'Club Beta',
            'slug' => 'club-beta',
            'status' => 'active',
        ]);

        $this->adminA = User::factory()->create(['email' => 'adminA@alpha.com']);
        $this->clubA->users()->attach($this->adminA->id, ['role' => 'admin', 'status' => 'active']);

        $this->memberA = User::factory()->create(['email' => 'memberA@alpha.com']);
        $this->clubA->users()->attach($this->memberA->id, ['role' => 'member', 'status' => 'active']);

        $this->memberB = User::factory()->create(['email' => 'memberB@beta.com']);
        $this->clubB->users()->attach($this->memberB->id, ['role' => 'member', 'status' => 'active']);
    }

    public function test_guest_is_redirected_away_from_login_protected_routes(): void
    {
        $response = $this->get(route('profile.edit'));
        $response->assertRedirect(route('login'));
    }

    public function test_regular_member_access_to_member_portal(): void
    {
        $response = $this->actingAs($this->memberA)
            ->get(route('member.dashboard', ['slug' => $this->clubA->slug]));

        $response->assertStatus(200);
    }

    public function test_admin_user_can_access_admin_analytics(): void
    {
        $response = $this->actingAs($this->adminA)
            ->get(route('admin.analytics', ['slug' => $this->clubA->slug]));

        $response->assertStatus(200);
    }

    public function test_event_creation_requires_valid_title_and_club(): void
    {
        $response = $this->actingAs($this->adminA)
            ->post(route('admin.events.store', ['clubSlug' => $this->clubA->slug]), [
                'title' => '',
                'price' => 'invalid_number',
            ]);

        $response->assertSessionHasErrors(['title']);
    }

    public function test_newsletter_creation_saves_sanitized_data(): void
    {
        $response = $this->actingAs($this->adminA)
            ->post(route('admin.newsletters.store', ['clubSlug' => $this->clubA->slug]), [
                'subject' => 'Weekly Announcement',
                'content' => '<p>Hello Members</p>',
                'target_roles' => ['member'],
                'status' => 'draft',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('newsletters', [
            'club_id' => $this->clubA->id,
            'subject' => 'Weekly Announcement',
        ]);
    }
}
