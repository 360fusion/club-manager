<?php

namespace Tests\Feature;

use App\Models\Club;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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

    public function test_club_and_club_type_factories_make_a_usable_club(): void
    {
        $club = Club::factory()->create();

        $this->assertSame('active', $club->status);
        $this->assertNotNull($club->clubType);
        $this->getJson("/api/v1/clubs/{$club->slug}/info")->assertOk()->assertJsonPath('club.slug', $club->slug);
    }
}
