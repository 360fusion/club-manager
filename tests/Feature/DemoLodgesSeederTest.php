<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;
use App\Models\Event;
use App\Models\EventPaymentMethod;
use App\Models\Meeting;
use App\Models\User;
use Database\Seeders\DemoLodgesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DemoLodgesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_needs_a_password_of_its_own(): void
    {
        config(['app.demo_password' => null]);

        $this->expectException(RuntimeException::class);
        $this->seed(DemoLodgesSeeder::class);
    }

    public function test_it_never_runs_on_production(): void
    {
        config(['app.demo_password' => 'a-long-demo-password']);
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $this->seed(DemoLodgesSeeder::class);
            $this->fail('The seeder ran on production.');
        } catch (RuntimeException) {
            $this->assertSame(0, User::count());
        }
    }

    public function test_it_builds_the_lodges_and_accounts_and_can_run_again(): void
    {
        config(['app.demo_password' => 'a-long-demo-password']);

        $this->seed(DemoLodgesSeeder::class);
        $counts = [User::count(), Club::count(), Member::count(), Meeting::count(), Event::count(), EventPaymentMethod::count()];
        $this->seed(DemoLodgesSeeder::class);

        $this->assertSame($counts, [User::count(), Club::count(), Member::count(), Meeting::count(), Event::count(), EventPaymentMethod::count()]);
        $this->assertSame(['demo-harmony-chapter', 'demo-harmony-lodge', 'demo-st-albans-lodge'], Club::orderBy('slug')->pluck('slug')->all());

        $harmony = Club::where('slug', 'demo-harmony-lodge')->first();
        $roles = $harmony->users()->pluck('club_user.role', 'email')->all();
        $this->assertSame('owner', $roles['demo-master@example.com']);
        $this->assertSame('coach', $roles['demo-secretary@example.com']);
        $this->assertSame('pending', $harmony->users()->where('email', 'demo-pending@example.com')->first()->pivot->status);
        $this->assertSame(0, User::where('email', 'demo-visitor@example.com')->first()->clubs()->count());
        $twoLodges = User::where('email', 'demo-two-lodges@example.com')->first();
        $this->assertSame(3, $twoLodges->clubs()->count());
        $this->assertSame('owner', $twoLodges->clubs()->where('slug', 'demo-st-albans-lodge')->first()->pivot->role);

        $this->assertTrue(Hash::check('a-long-demo-password', User::where('email', 'demo-member@example.com')->first()->password));
        $this->assertSame(1, Meeting::where('club_id', $harmony->id)->where('status', 'published')->whereDate('meeting_date', '>', now())->count());
        $this->assertSame(2, EventPaymentMethod::count(), 'The festival offers bank transfer and paying on the night.');

        // Signing in works and the member lands in their lodge.
        $this->post('/login', ['email' => 'demo-member@example.com', 'password' => 'a-long-demo-password'])->assertRedirect();
        $this->assertAuthenticatedAs(User::where('email', 'demo-member@example.com')->first());
        $this->get('/members/demo-harmony-lodge')->assertOk();
    }
}
