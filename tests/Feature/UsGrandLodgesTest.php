<?php

namespace Tests\Feature;

use App\Models\GrandLodge;
use App\Models\User;
use Database\Seeders\UsGrandLodgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsGrandLodgesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_us_grand_lodges_page(): void
    {
        $response = $this->get('/superadmin/us-grand-lodges');

        $response->assertRedirect('/login');
    }

    public function test_non_superadmin_is_redirected_away_from_us_grand_lodges_page(): void
    {
        $user = User::factory()->create(['is_super_admin' => false]);

        $response = $this->actingAs($user)->get('/superadmin/us-grand-lodges');

        $response->assertRedirect('/');
    }

    public function test_superadmin_can_view_us_grand_lodges_page(): void
    {
        $superadmin = User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($superadmin)->get('/superadmin/us-grand-lodges');

        $response->assertStatus(200);
    }

    public function test_seeder_creates_all_fifty_one_us_jurisdictions(): void
    {
        $this->seed(UsGrandLodgeSeeder::class);

        $this->assertSame(51, GrandLodge::where('country', 'United States')->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(UsGrandLodgeSeeder::class);
        $this->seed(UsGrandLodgeSeeder::class);

        $this->assertSame(51, GrandLodge::where('country', 'United States')->count());
    }

    public function test_us_codes_use_the_usgl_prefix_and_do_not_collide(): void
    {
        $this->seed(UsGrandLodgeSeeder::class);

        $codes = GrandLodge::where('country', 'United States')->pluck('code');

        $this->assertCount(51, $codes->unique());

        foreach ($codes as $code) {
            $this->assertStringStartsWith('usgl', $code);
        }
    }

    public function test_page_only_receives_us_grand_lodges(): void
    {
        $superadmin = User::factory()->create(['is_super_admin' => true]);

        $this->seed(UsGrandLodgeSeeder::class);

        GrandLodge::create([
            'name' => 'Grand Lodge of Elsewhere',
            'code' => 'glelsewhere',
            'short_name' => 'GLE Test',
            'country' => 'Australia',
        ]);

        $response = $this->actingAs($superadmin)->get('/superadmin/us-grand-lodges');

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page
                ->component('SuperAdmin/GrandLodges/UnitedStates')
                ->has('usGrandLodges', 51)
        );
    }
}
