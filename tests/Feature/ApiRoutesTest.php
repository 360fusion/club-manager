<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_routes_are_stateless_so_app_clients_need_no_csrf_token_or_session(): void
    {
        foreach (['api.tokens.create', 'api.clubs.info'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();

            $this->assertContains('api', $middleware, "{$name} should use the api group");
            $this->assertNotContains('web', $middleware, "{$name} must not use the web group (CSRF and session)");
        }
    }

    public function test_info_route_is_public_and_the_user_route_needs_a_token(): void
    {
        $this->getJson('/api/v1/user')->assertUnauthorized();
        $this->getJson('/api/v1/clubs/nope/info')->assertNotFound();
    }

    public function test_the_user_route_returns_only_what_an_app_needs(): void
    {
        $user = User::factory()->create(['avatar_url' => 'https://example.com/a.png']);
        $user->forceFill(['stripe_id' => 'cus_123', 'pm_last_four' => '4242', 'is_super_admin' => true])->save();
        Sanctum::actingAs($user, ['profile:read']);

        $this->getJson('/api/v1/user')->assertOk()->assertExactJson([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => 'https://example.com/a.png',
        ]);
    }

    public function test_club_and_club_type_factories_make_a_usable_club(): void
    {
        $club = Club::factory()->create();

        $this->assertSame('active', $club->status);
        $this->assertNotNull($club->clubType);
        $this->getJson("/api/v1/clubs/{$club->slug}/info")->assertOk()->assertJsonPath('club.slug', $club->slug);
    }
}
