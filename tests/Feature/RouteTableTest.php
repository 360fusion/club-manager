<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Which named routes a page sends the browser (config/ziggy.php): small for visitors and plain members, who are most phones. */
class RouteTableTest extends TestCase
{
    use RefreshDatabase;

    private function memberOf(Club $club, string $role): User
    {
        $user = User::factory()->create();
        $club->users()->attach($user->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    /**
     * The route table a page sends: the kept file the page points to (ZiggyScript), fetched as the same visitor.
     */
    private function routeTable(string $page): string
    {
        preg_match('#<script src="(/ziggy/[^"]+)"></script>#', $this->get($page)->assertOk()->getContent(), $found);
        $this->assertNotEmpty($found, 'the page points to its route table');

        return $this->get($found[1])->assertOk()->getContent();
    }

    public function test_a_logged_out_visitor_is_not_given_the_admin_or_superadmin_routes(): void
    {
        $html = $this->routeTable('/lodges');

        $this->assertStringContainsString('lodges.index', $html);
        $this->assertStringNotContainsString('superadmin.dashboard', $html);
        $this->assertStringNotContainsString('admin.pages.index', $html);
        $this->assertStringNotContainsString('make_me_superadmin', $html);
    }

    public function test_a_plain_member_is_sent_no_club_admin_routes_but_keeps_the_security_page(): void
    {
        $this->actingAs($this->memberOf(Club::factory()->create(), 'member'));
        $table = $this->routeTable(route('members.dashboard', [], false));

        foreach (['"admin.accounting.index"', '"admin.pages.index"', '"admin.analytics"', 'superadmin.dashboard'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $table);
        }
        $this->assertStringContainsString('"admin.profile.two-factor"', $table);
        $this->assertStringContainsString('"members.notifications"', $table);
    }

    public function test_staff_and_office_holders_get_the_admin_routes_but_not_the_superadmin_ones(): void
    {
        $club = Club::factory()->create();
        $this->actingAs($this->memberOf($club, 'treasurer'));
        $table = $this->routeTable('/lodges');
        $this->assertStringContainsString('"admin.accounting.index"', $table);
        $this->assertStringContainsString('"admin.pages.index"', $table);
        $this->assertStringNotContainsString('superadmin.dashboard', $table);
        $this->assertStringNotContainsString('make_me_superadmin', $table);

        $officer = $this->memberOf($club, 'member');
        Member::create(['club_id' => $club->id, 'user_id' => $officer->id, 'first_name' => 'Jo', 'last_name' => 'Lee', 'membership_status' => 'active', 'current_office' => 'treasurer']);
        $this->actingAs($officer);
        $this->assertStringContainsString('"admin.accounting.index"', $this->routeTable(route('members.dashboard', [], false)));
    }

    public function test_a_super_admin_gets_every_route(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
        $html = $this->routeTable('/lodges');

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
