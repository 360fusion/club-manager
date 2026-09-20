<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminPrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_promotion_route_is_unavailable_outside_local_development(): void
    {
        $user = User::factory()->create(['is_super_admin' => false]);

        $response = $this->actingAs($user)->get('/auth/make-me-superadmin');

        $response->assertNotFound();
        $this->assertFalse($user->fresh()->is_super_admin);
    }

    public function test_super_admin_flag_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create(['is_super_admin' => false]);

        $user->fill(['is_super_admin' => true]);

        $this->assertFalse($user->is_super_admin);
    }

    public function test_super_admin_flag_is_not_set_by_creating_a_user_from_request_style_input(): void
    {
        $user = User::create([
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'password',
            'is_super_admin' => true,
        ]);

        $this->assertFalse((bool) $user->fresh()->is_super_admin);
    }

    public function test_grant_command_promotes_and_revokes_a_user(): void
    {
        $user = User::factory()->create(['is_super_admin' => false]);

        $this->artisan('superadmin:grant', ['email' => $user->email])
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->is_super_admin);

        $this->artisan('superadmin:grant', ['email' => $user->email, '--revoke' => true])
            ->assertSuccessful();

        $this->assertFalse($user->fresh()->is_super_admin);
    }

    public function test_grant_command_fails_for_an_unknown_email(): void
    {
        $this->artisan('superadmin:grant', ['email' => 'nobody@example.com'])
            ->assertFailed();
    }
}
