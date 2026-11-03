<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Account protections brought over from launchpad: a second factor for superadmins, the current password before
 * anything that hands over or weakens an account, and recovery codes that are only shown once.
 */
class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function withTwoFactor(User $user): User
    {
        $user->forceFill([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code-one', 'code-two'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    public function test_a_superadmin_needs_two_factor_on_a_real_server(): void
    {
        $superadmin = User::factory()->create(['is_super_admin' => true]);
        $this->app['env'] = 'production';

        $this->actingAs($superadmin)->get('/superadmin')->assertRedirect(route('admin.profile.two-factor'));

        $this->withTwoFactor($superadmin);
        $this->actingAs($superadmin->fresh())->get('/superadmin')->assertOk();
    }

    public function test_the_security_page_does_not_show_the_secret_or_recovery_codes_again(): void
    {
        $user = $this->withTwoFactor(User::factory()->create());

        $this->actingAs($user)->get(route('admin.profile.two-factor'))
            ->assertDontSee('code-one')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('secretKey', null)
                ->where('recoveryCodes', [])
                ->where('recoveryCodesLeft', 2));
    }

    public function test_turning_two_factor_off_or_making_new_codes_needs_the_current_password(): void
    {
        $user = $this->withTwoFactor(User::factory()->create(['password' => Hash::make('right-password')]));

        $this->actingAs($user)->delete(route('admin.two-factor.disable'))->assertSessionHasErrors('password');
        $this->actingAs($user)->post(route('admin.two-factor.recovery-codes'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        // Fortify's own route is behind password confirmation too.
        $this->actingAs($user)->delete('/user/two-factor-authentication')->assertRedirect();
        $this->assertNotNull($user->fresh()->two_factor_secret);

        $this->actingAs($user)->post(route('admin.two-factor.recovery-codes'), ['password' => 'right-password'])
            ->assertSessionHas('recoveryCodes');
        $this->actingAs($user)->delete(route('admin.two-factor.disable'), ['password' => 'right-password'])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_changing_the_sign_in_email_needs_the_current_password(): void
    {
        $user = User::factory()->create(['email' => 'old@example.test', 'password' => Hash::make('right-password')]);

        $this->actingAs($user)->put(route('profile.update'), ['name' => 'New Name', 'email' => 'old@example.test'])->assertSessionHasNoErrors();
        $this->actingAs($user)->put(route('profile.update'), ['name' => 'New Name', 'email' => 'thief@example.test'])->assertSessionHasErrors('current_password');
        $this->assertSame('old@example.test', $user->fresh()->email);

        $this->actingAs($user)->put(route('profile.update'), ['name' => 'New Name', 'email' => 'new@example.test', 'current_password' => 'right-password'])->assertSessionHasNoErrors();
        $this->assertSame('new@example.test', $user->fresh()->email);
    }

    public function test_a_password_change_ends_other_sessions(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $session = ['password_hash_web' => $user->getAuthPassword()];

        $this->actingAs($user)->withSession($session)->get(route('admin.profile.two-factor'))->assertOk();

        $user->forceFill(['password' => Hash::make('changed-elsewhere')])->save();

        $this->actingAs($user->fresh())->withSession($session)->get(route('admin.profile.two-factor'))->assertRedirect(route('login'));
    }

    public function test_signing_out_other_devices_needs_the_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('right-password')]);

        $this->actingAs($user)->post(route('admin.security.logout-others'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->actingAs($user)->post(route('admin.security.logout-others'), ['password' => 'right-password'])->assertSessionHas('success');
    }
}
