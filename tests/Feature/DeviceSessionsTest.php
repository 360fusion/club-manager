<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DeviceSessions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeviceSessionsTest extends TestCase
{
    use RefreshDatabase;

    private function signedInOn(User $user, string $id, string $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit Chrome/120 Safari/537'): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'ip_address' => '203.0.113.9', 'user_agent' => $userAgent, 'payload' => '', 'last_activity' => now()->subMinutes(5)->timestamp]);
    }

    public function test_the_list_names_each_device_and_marks_the_current_one(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $this->signedInOn($user, 'here');
        $this->signedInOn($user, 'phone', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit Safari/604');
        $this->signedInOn(User::factory()->create(), 'someone-else');

        $devices = collect(DeviceSessions::for($user, 'here'));

        $this->assertCount(2, $devices);
        $this->assertSame('Chrome on Mac', $devices->firstWhere('id', 'here')['device']);
        $this->assertTrue($devices->firstWhere('id', 'here')['current']);
        $this->assertSame('Safari on iPhone or iPad', $devices->firstWhere('id', 'phone')['device']);
        $this->assertFalse($devices->firstWhere('id', 'phone')['current']);
    }

    public function test_there_is_no_list_when_sessions_are_not_in_the_database(): void
    {
        config(['session.driver' => 'file']);
        $user = User::factory()->create();
        $this->signedInOn($user, 'x');

        $this->assertSame([], DeviceSessions::for($user, 'y'));
    }

    public function test_signing_a_device_out_needs_the_password_and_only_touches_your_own_sessions(): void
    {
        $me = User::factory()->create(['password' => Hash::make('right-password')]);
        $other = User::factory()->create();
        $this->signedInOn($me, 'phone');
        $this->signedInOn($other, 'theirs');

        $this->actingAs($me)->deleteJson(route('account.devices.destroy', 'phone'), ['password' => 'wrong'])->assertJsonValidationErrors('password');
        $this->assertDatabaseHas('sessions', ['id' => 'phone']);

        $this->actingAs($me)->delete(route('account.devices.destroy', 'theirs'), ['password' => 'right-password']);
        $this->assertDatabaseHas('sessions', ['id' => 'theirs']);

        $this->actingAs($me)->delete(route('account.devices.destroy', 'phone'), ['password' => 'right-password']);
        $this->assertDatabaseMissing('sessions', ['id' => 'phone']);
    }

    public function test_the_session_making_the_request_is_not_signed_out_by_this_route(): void
    {
        $me = User::factory()->create(['password' => Hash::make('right-password')]);

        $this->assertFalse(DeviceSessions::revoke($me, 'current', 'current'));
    }

    public function test_the_security_page_carries_the_device_list(): void
    {
        config(['session.driver' => 'database']);
        $me = User::factory()->create();
        $this->signedInOn($me, 'phone');

        $this->actingAs($me)->get(route('admin.profile.two-factor'))->assertInertia(fn ($page) => $page->has('devices', 1)->where('devices.0.id', 'phone'));
    }
}
