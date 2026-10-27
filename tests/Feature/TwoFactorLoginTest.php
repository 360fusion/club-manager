<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorLoginTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'JBSWY3DPEHPK3PXP';

    private function userWithTwoFactor(): User
    {
        return User::factory()->create([
            'password' => bcrypt('Passw0rd!long'),
            'two_factor_secret' => encrypt(self::SECRET),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function test_password_login_sends_a_two_factor_user_to_the_code_page(): void
    {
        $user = $this->userWithTwoFactor();

        $this->post('/login', ['email' => $user->email, 'password' => 'Passw0rd!long'])->assertRedirect(route('two-factor.login'));
        $this->assertGuest();

        $this->get(route('two-factor.login'))->assertOk();
    }

    public function test_code_page_needs_a_password_step_first(): void
    {
        $this->get(route('two-factor.login'))->assertRedirect(route('login'));
    }

    public function test_right_code_signs_in_and_wrong_code_goes_back_to_the_code_page(): void
    {
        $user = $this->userWithTwoFactor();
        $this->post('/login', ['email' => $user->email, 'password' => 'Passw0rd!long']);

        $this->post('/two-factor-challenge', ['code' => '000000'])->assertRedirect(route('two-factor.login'))->assertSessionHasErrors();
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp(self::SECRET)])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_api_tokens_are_issued_without_two_factor_but_refused_with_it(): void
    {
        $plain = User::factory()->create(['password' => bcrypt('Passw0rd!long')]);
        $secured = $this->userWithTwoFactor();

        $this->postJson('/api/v1/tokens/create', ['email' => $plain->email, 'password' => 'Passw0rd!long', 'device_name' => 'phone'])->assertOk()->assertJsonStructure(['token']);
        $this->postJson('/api/v1/tokens/create', ['email' => $secured->email, 'password' => 'Passw0rd!long', 'device_name' => 'phone'])->assertForbidden();
        $this->assertSame(0, $secured->tokens()->count());
    }
}
