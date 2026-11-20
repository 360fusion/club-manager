<?php

namespace Tests\Feature;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\PushDevice;
use App\Models\User;
use App\Support\AccountPurging;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Passkeys\Events\PasskeyRegistered;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use Tests\TestCase;

class PasskeySignInTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sign_in_page_can_ask_for_a_passkey(): void
    {
        $this->getJson('/passkeys/login/options')
            ->assertOk()
            ->assertJsonStructure(['options' => ['challenge']]);
    }

    public function test_adding_a_passkey_needs_the_password_confirmed_first(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/user/passkeys/options')->assertStatus(423);

        $this->actingAs($user)->postJson('/user/confirm-password', ['password' => 'password'])->assertCreated();
        $this->actingAs($user)->getJson('/user/passkeys/options')
            ->assertOk()
            ->assertJsonPath('options.user.name', $user->email);
    }

    public function test_a_wrong_password_adds_nothing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/user/confirm-password', ['password' => 'not-it'])->assertUnprocessable();
        $this->actingAs($user)->getJson('/user/passkeys/options')->assertStatus(423);
    }

    public function test_the_security_page_lists_the_members_passkeys(): void
    {
        $user = User::factory()->create();
        $user->passkeys()->create(['name' => 'iPhone', 'credential_id' => 'abc', 'credential' => ['id' => 'abc']]);

        $this->actingAs($user)->get(route('admin.profile.two-factor'))
            ->assertInertia(fn ($page) => $page->where('passkeys.0.name', 'iPhone')->where('passkeys.0.last_used', null));
    }

    private function passkeyFor(User $user): Passkey
    {
        return $user->passkeys()->create(['name' => 'iPhone', 'credential_id' => 'cred-'.$user->id, 'credential' => ['id' => 'x']]);
    }

    public function test_a_confirmed_password_does_not_open_fortifys_own_two_factor_pages(): void
    {
        $user = User::factory()->create();
        app(EnableTwoFactorAuthentication::class)($user);
        $this->actingAs($user)->postJson('/user/confirm-password', ['password' => 'password'])->assertCreated();

        $this->actingAs($user)->getJson('/user/two-factor-recovery-codes')->assertNotFound();
        $this->actingAs($user)->getJson('/user/two-factor-secret-key')->assertNotFound();
        $this->actingAs($user)->deleteJson('/user/two-factor-authentication')->assertNotFound();
        $this->assertNotNull($user->fresh()->two_factor_secret);
    }

    public function test_guessing_the_password_on_the_confirm_step_is_slowed_down(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 10) as $ignored) {
            $this->actingAs($user)->postJson('/user/confirm-password', ['password' => 'wrong']);
        }

        $this->actingAs($user)->postJson('/user/confirm-password', ['password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_each_passkey_added_needs_the_password_again(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/user/confirm-password', ['password' => 'password'])->assertCreated();
        $this->assertNotNull(session('auth.password_confirmed_at'));

        event(new PasskeyRegistered($user, $this->passkeyFor($user)));

        $this->assertNull(session('auth.password_confirmed_at'));
    }

    public function test_a_new_password_or_a_purged_account_removes_every_passkey_and_device(): void
    {
        $user = User::factory()->create();
        $this->passkeyFor($user);
        app(ResetUserPassword::class)->reset($user, ['password' => 'A-much-longer-password-1!', 'password_confirmation' => 'A-much-longer-password-1!']);
        $this->assertSame(0, $user->passkeys()->count());

        $this->passkeyFor($user);
        PushDevice::factory()->create(['user_id' => $user->id]);
        AccountPurging::purge($user);
        $this->assertSame(0, $user->passkeys()->count());
        $this->assertSame(0, PushDevice::where('user_id', $user->id)->count());
    }

    public function test_a_passkey_cannot_sign_in_a_purged_account_or_one_whose_email_is_unconfirmed(): void
    {
        config(['auth.require_email_verification' => true]);
        $request = Request::create('/passkeys/login', 'POST');

        $verified = User::factory()->create();
        $unconfirmed = User::factory()->unverified()->create();
        $purged = User::factory()->create(['purged_at' => now()]);

        $this->assertTrue(Passkeys::allowsLogin($request, $this->passkeyFor($verified)));
        $this->assertFalse(Passkeys::allowsLogin($request, $this->passkeyFor($unconfirmed)));
        $this->assertFalse(Passkeys::allowsLogin($request, $this->passkeyFor($purged)));
    }
}
