<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubSettingsTest extends TestCase
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
            'available_modules' => ['website_builder', 'memberships', 'events', 'newsletters'],
            'default_settings' => [],
        ]);

        $this->club = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'The Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'status' => 'active',
            'settings' => [
                'contact_email' => 'info@oxfordrowing.co.uk',
                'phone' => '+44 1865 123456',
            ],
        ]);

        $this->adminUser = User::factory()->create();
        $this->club->users()->attach($this->adminUser->id, [
            'role' => 'admin',
            'member_number' => 'OUBC-001',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_access_club_settings_page(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.settings.show', ['clubSlug' => $this->club->slug]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Settings/Show')
            ->has('club')
            ->has('settings')
            ->has('allModules')
            ->has('availableRoles')
        );
    }

    public function test_admin_can_update_general_and_branding_settings(): void
    {
        $payload = [
            'contact_email' => 'updated@oxfordrowing.co.uk',
            'phone' => '+44 1865 999888',
            'address' => 'Boathouse 1, River Isis, Oxford',
            'primary_color' => '#0369a1',
            'timezone' => 'Europe/London',
            'currency' => 'GBP',
        ];

        $response = $this->actingAs($this->adminUser)
            ->put(route('admin.settings.update', ['clubSlug' => $this->club->slug]), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Club settings updated successfully.');

        $this->club->refresh();
        $this->assertEquals('updated@oxfordrowing.co.uk', $this->club->settings['contact_email']);
        $this->assertEquals('#0369a1', $this->club->settings['primary_color']);
    }

    public function test_admin_can_update_role_permissions_matrix(): void
    {
        $payload = [
            'permission_matrix' => [
                'manage_members' => [
                    'roles' => ['owner', 'admin', 'treasurer'],
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->put(route('admin.settings.update', ['clubSlug' => $this->club->slug]), $payload);

        $response->assertRedirect();

        $this->club->refresh();
        $this->assertEquals(['owner', 'admin', 'treasurer'], $this->club->settings['permission_matrix']['manage_members']['roles']);
    }

    public function test_admin_can_update_active_modules_and_custom_domain(): void
    {
        $payload = [
            'custom_domain' => 'rowing.oxford.ac.uk',
            'enabled_modules' => ['website_builder', 'memberships', 'events'],
            'notify_event_reminders' => true,
        ];

        $response = $this->actingAs($this->adminUser)
            ->put(route('admin.settings.update', ['clubSlug' => $this->club->slug]), $payload);

        $response->assertRedirect();

        $this->club->refresh();
        $this->assertEquals('rowing.oxford.ac.uk', $this->club->custom_domain);
        $this->assertEquals(['website_builder', 'memberships', 'events'], $this->club->settings['enabled_modules']);
    }
}
