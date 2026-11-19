<?php

namespace Tests\Feature;

use App\Actions\Fortify\UpdateUserPassword;
use App\Models\DeviceSignInKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeviceSignInTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
    }

    /**
     * A key as the app gets it straight after signing in with the password.
     */
    private function turnOn(): string
    {
        return $this->actingAs($this->user)->withSession(['auth.signed_in_at' => time()])
            ->postJson(route('members.sign_in_keys.store'), ['platform' => 'ios', 'name' => 'iPhone'])
            ->assertOk()
            ->json('key');
    }

    public function test_a_phone_turned_on_after_signing_in_can_sign_back_in_with_its_key(): void
    {
        $key = $this->turnOn();

        $this->assertSame(64, strlen($key));
        $this->assertNotSame($key, DeviceSignInKey::sole()->key_hash, 'only a hash is stored');

        auth()->logout();
        $this->post(route('login.device'), ['key' => $key])->assertRedirect(route('members.dashboard'));
        $this->assertAuthenticatedAs($this->user);
        $this->assertNotNull(DeviceSignInKey::sole()->last_used_at);
    }

    public function test_turning_it_on_later_needs_the_password(): void
    {
        $this->actingAs($this->user)->withSession(['auth.signed_in_at' => time() - 3600])
            ->postJson(route('members.sign_in_keys.store'), ['platform' => 'android'])
            ->assertJsonValidationErrors('password');

        $this->actingAs($this->user)->withSession(['auth.signed_in_at' => time() - 3600])
            ->postJson(route('members.sign_in_keys.store'), ['platform' => 'android', 'password' => 'correct-horse-battery'])
            ->assertOk();

        $this->assertSame(1, DeviceSignInKey::count());
    }

    public function test_a_wrong_stale_or_revoked_key_does_not_sign_in(): void
    {
        $key = $this->turnOn();
        auth()->logout();

        $this->post(route('login.device'), ['key' => str_repeat('x', 64)])->assertSessionHasErrors('device_key');

        DeviceSignInKey::sole()->forceFill(['last_used_at' => now()->subDays(DeviceSignInKey::UNUSED_DAYS + 1)])->save();
        $this->post(route('login.device'), ['key' => $key])->assertSessionHasErrors('device_key');
        $this->assertSame(0, DeviceSignInKey::count(), 'a stale key is removed');

        $this->assertGuest();
    }

    public function test_a_new_password_or_turning_it_off_removes_the_key(): void
    {
        $this->turnOn();
        app(UpdateUserPassword::class)->update($this->user, ['current_password' => 'correct-horse-battery', 'password' => 'Another-Long-Pass-123!', 'password_confirmation' => 'Another-Long-Pass-123!']);
        $this->assertSame(0, $this->user->deviceSignInKeys()->count());

        $this->flushSession();
        $this->turnOn();
        $other = User::factory()->create();
        $this->flushSession();
        $this->actingAs($other)->deleteJson(route('members.sign_in_keys.destroy', DeviceSignInKey::sole()->id))->assertOk();
        $this->assertSame(1, DeviceSignInKey::count(), 'someone else cannot remove it');

        $this->flushSession();
        $this->actingAs($this->user)->deleteJson(route('members.sign_in_keys.destroy', DeviceSignInKey::sole()->id))->assertOk();
        $this->assertSame(0, DeviceSignInKey::count());
    }

    public function test_signing_in_with_a_password_marks_the_session_as_just_signed_in(): void
    {
        $this->post('/login', ['email' => $this->user->email, 'password' => 'correct-horse-battery'])->assertSessionHas('auth.signed_in_at');

        $this->assertAuthenticatedAs($this->user);
    }
}
