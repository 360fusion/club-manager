<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Launchpad\Testing\AssertsRoutesAreClassified;
use Tests\TestCase;

/**
 * Put here by `launchpad:install`: the pages Launchpad gives the app all answer, a person can sign up, and no route has been left open by
 * accident. Keep it; it is what catches a package update that breaks the app.
 */
class LaunchpadSmokeTest extends TestCase
{
    use AssertsRoutesAreClassified;
    use RefreshDatabase;

    public function test_the_public_pages_answer(): void
    {
        foreach (['/', '/pricing', '/help', '/faq', '/contact', '/login', '/register', '/forgot-password', '/privacy', '/terms', '/robots.txt', '/sitemap.xml'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_a_person_can_sign_up_and_reach_their_workspace(): void
    {
        $this->post('/register', ['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'a-long-passw0rd', 'password_confirmation' => 'a-long-passw0rd', 'terms' => '1'])
            ->assertRedirect(route('verification.notice'));

        $this->assertAuthenticated();
        $this->get('/workspaces')->assertOk()->assertSee('Personal');
    }

    public function test_the_admin_is_closed_to_the_public(): void
    {
        $this->get('/hq')->assertRedirect('/hq/login');
    }

    public function test_every_route_is_classified(): void
    {
        $this->assertEveryRouteIsClassified();
    }
}
