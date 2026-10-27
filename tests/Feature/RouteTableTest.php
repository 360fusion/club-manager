<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_logged_out_visitor_is_not_given_the_admin_or_superadmin_routes(): void
    {
        $html = $this->get('/lodges')->assertOk()->getContent();

        $this->assertStringContainsString('lodges.index', $html);
        $this->assertStringNotContainsString('superadmin.dashboard', $html);
        $this->assertStringNotContainsString('admin.pages.index', $html);
        $this->assertStringNotContainsString('make_me_superadmin', $html);
    }

    public function test_a_member_gets_the_admin_routes_but_not_the_superadmin_ones(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/lodges')->assertOk()->getContent();

        $this->assertStringContainsString('admin.pages.index', $html);
        $this->assertStringNotContainsString('superadmin.dashboard', $html);
        $this->assertStringNotContainsString('make_me_superadmin', $html);
    }

    public function test_a_super_admin_gets_every_route(): void
    {
        $html = $this->actingAs(User::factory()->create(['is_super_admin' => true]))->get('/lodges')->assertOk()->getContent();

        $this->assertStringContainsString('superadmin.dashboard', $html);
    }

    public function test_signing_in_makes_the_next_page_a_full_load_so_the_route_table_is_replaced(): void
    {
        preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $this->get('/lodges')->assertOk()->getContent(), $found);
        $guestVersion = json_decode($found[1], true)['version'];

        $this->actingAs(User::factory()->create());

        $this->get('/lodges', ['X-Inertia' => 'true', 'X-Inertia-Version' => $guestVersion])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location');
    }
}
