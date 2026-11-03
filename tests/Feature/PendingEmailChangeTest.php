<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use App\Notifications\ConfirmNewEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** A new sign-in email is held until its link is opened, so a typo cannot lock anyone out */
class PendingEmailChangeTest extends TestCase
{
    use RefreshDatabase;

    private function person(): User
    {
        return User::factory()->create(['email' => 'old@example.test', 'email_verified_at' => now(), 'password' => Hash::make('right-password')]);
    }

    private function change(User $user, string $email = 'new@example.test')
    {
        return $this->actingAs($user)->put(route('profile.update'), ['name' => $user->name, 'email' => $email, 'current_password' => 'right-password']);
    }

    private function confirmUrl(User $user): string
    {
        return URL::temporarySignedRoute('account.email.confirm', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->pending_email)]);
    }

    public function test_the_new_address_is_held_and_the_old_one_keeps_working(): void
    {
        config(['auth.require_email_verification' => true]);
        Notification::fake();
        $user = $this->person();

        $this->change($user)->assertSessionHas('success');

        $user = $user->fresh();
        $this->assertSame('old@example.test', $user->email);
        $this->assertSame('new@example.test', $user->pending_email);
        $this->assertNotNull($user->email_verified_at);
        Notification::assertSentOnDemand(ConfirmNewEmail::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'new@example.test');
    }

    public function test_opening_the_link_swaps_the_address_and_verifies_it(): void
    {
        config(['auth.require_email_verification' => true]);
        Notification::fake();
        $user = $this->person();
        $this->change($user);

        // Still signed in here (actingAs carries over), so the redirect goes to their profile.
        $this->get($this->confirmUrl($user->fresh()))->assertRedirect(route('profile.edit'));

        $user = $user->fresh();
        $this->assertSame('new@example.test', $user->email);
        $this->assertNull($user->pending_email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_the_link_works_from_a_browser_that_is_not_signed_in(): void
    {
        $user = $this->person();
        $user->forceFill(['pending_email' => 'new@example.test'])->save();

        $this->get($this->confirmUrl($user))->assertRedirect(route('login'));

        $this->assertSame('new@example.test', $user->fresh()->email);
    }

    public function test_a_link_for_an_address_that_is_not_pending_or_is_not_signed_does_nothing(): void
    {
        config(['auth.require_email_verification' => true]);
        Notification::fake();
        $user = $this->person();
        $this->change($user);
        $url = $this->confirmUrl($user->fresh());

        $this->get(preg_replace('/signature=.*/', 'signature=bad', $url))->assertForbidden();

        // Changing the pending address (a second request) kills the first link.
        $this->change($user->fresh(), 'third@example.test');
        $this->get($url)->assertForbidden();
        $this->assertSame('old@example.test', $user->fresh()->email);
    }

    public function test_the_address_is_not_taken_if_someone_else_claimed_it_meanwhile(): void
    {
        config(['auth.require_email_verification' => true]);
        Notification::fake();
        $user = $this->person();
        $this->change($user);
        $url = $this->confirmUrl($user->fresh());
        User::factory()->create(['email' => 'new@example.test']);

        $this->get($url)->assertRedirect()->assertSessionHas('error');

        $this->assertSame('old@example.test', $user->fresh()->email);
        $this->assertNull($user->fresh()->pending_email);
    }

    public function test_the_change_can_be_cancelled(): void
    {
        config(['auth.require_email_verification' => true]);
        Notification::fake();
        $user = $this->person();
        $this->change($user);

        $this->actingAs($user->fresh())->delete(route('account.email.cancel'));

        $this->assertNull($user->fresh()->pending_email);
    }

    public function test_where_mail_is_only_logged_the_address_changes_at_once_as_before(): void
    {
        config(['auth.require_email_verification' => false]);
        Notification::fake();
        $user = $this->person();

        $this->change($user);

        $this->assertSame('new@example.test', $user->fresh()->email);
        $this->assertNull($user->fresh()->pending_email);
        Notification::assertNothingSent();
    }

    public function test_the_current_password_is_still_needed_and_the_same_address_changes_nothing(): void
    {
        config(['auth.require_email_verification' => true]);
        Notification::fake();
        $user = $this->person();

        $this->actingAs($user)->put(route('profile.update'), ['name' => 'X', 'email' => 'new@example.test', 'current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->assertNull($user->fresh()->pending_email);

        $this->actingAs($user)->put(route('profile.update'), ['name' => 'Renamed', 'email' => 'OLD@example.test']);
        $this->assertSame('Renamed', $user->fresh()->name);
        $this->assertNull($user->fresh()->pending_email);
        Notification::assertNothingSent();
    }

    public function test_the_club_profile_page_holds_the_address_too(): void
    {
        config(['auth.require_email_verification' => true]);
        Notification::fake();
        $club = Club::factory()->create();
        $user = $this->makeClubAdmin($this->person(), $club, 'member');

        $this->actingAs($user)->post(route('member.profile.update', $club->slug), ['name' => $user->name, 'email' => 'new@example.test', 'current_password' => 'right-password']);

        $this->assertSame('old@example.test', $user->fresh()->email);
        $this->assertSame('new@example.test', $user->fresh()->pending_email);
    }
}
