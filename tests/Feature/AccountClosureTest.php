<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use App\Support\AccountClosure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** A person downloading their data or closing their account: the wait, what stops it, and what the purge removes and keeps */
class AccountClosureTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['password' => Hash::make('right-password')]);
    }

    public function test_closing_needs_the_current_password_and_starts_a_wait_that_can_be_cancelled(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('account.privacy.close'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertNull($user->fresh()->closure_requested_at);

        $this->actingAs($user)->post(route('account.privacy.close'), ['password' => 'right-password'])->assertSessionHas('success');
        $this->assertNotNull($user->fresh()->closure_requested_at);

        $this->actingAs($user)->delete(route('account.privacy.cancel'))->assertSessionHas('success');
        $this->assertNull($user->fresh()->closure_requested_at);
    }

    public function test_the_only_owner_of_a_club_cannot_close_until_someone_else_owns_it(): void
    {
        $club = Club::factory()->create(['name' => 'Lonely Lodge']);
        $owner = $this->makeClubAdmin($this->user(), $club, 'owner');

        $this->actingAs($owner)->post(route('account.privacy.close'), ['password' => 'right-password'])->assertSessionHasErrors('closure');
        $this->assertNull($owner->fresh()->closure_requested_at);

        $this->makeClubAdmin(User::factory()->create(), $club, 'owner');
        $this->actingAs($owner)->post(route('account.privacy.close'), ['password' => 'right-password'])->assertSessionHasNoErrors();
        $this->assertNotNull($owner->fresh()->closure_requested_at);
    }

    public function test_a_superadmin_cannot_close_their_account(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->assertNotEmpty(AccountClosure::blockers($admin));
    }

    public function test_the_purge_waits_the_full_thirty_days(): void
    {
        $early = $this->user();
        $early->forceFill(['closure_requested_at' => now()->subDays(29)])->save();
        $due = $this->user();
        $due->forceFill(['closure_requested_at' => now()->subDays(30)->subMinute()])->save();

        $this->artisan('accounts:purge-closed')->assertSuccessful();

        $this->assertNull($early->fresh()->purged_at);
        $this->assertNotNull($due->fresh()->purged_at);
    }

    public function test_the_purge_blanks_the_account_and_unlinks_clubs_records_without_deleting_them(): void
    {
        $club = Club::factory()->create();
        $other = Club::factory()->create();
        $user = $this->makeClubAdmin($this->user(), $club, 'member');
        $user->forceFill(['closure_requested_at' => now()->subDays(31), 'avatar_url' => 'https://x/a.png', 'calendar_token' => 'tok'])->save();
        $user->createToken('mine');
        DB::table('club_acc_members')->insert(['club_id' => $club->id, 'user_id' => $user->id, 'first_name' => 'Jo', 'last_name' => 'Blogs', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('accounting_contacts')->insert(['club_id' => $club->id, 'user_id' => $user->id, 'type' => 'customer', 'name' => 'Jo Blogs', 'created_at' => now(), 'updated_at' => now()]);
        $bystander = $this->makeClubAdmin(User::factory()->create(), $other, 'member');

        $this->artisan('accounts:purge-closed')->assertSuccessful();

        $user = $user->fresh();
        $this->assertSame('Deleted user', $user->name);
        $this->assertSame("deleted-{$user->id}@deleted.invalid", $user->email);
        $this->assertNull($user->avatar_url);
        $this->assertNull($user->calendar_token);
        $this->assertFalse(Hash::check('right-password', $user->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseMissing('club_user', ['user_id' => $user->id]);
        // The club's records survive, unlinked from the person.
        $this->assertDatabaseHas('club_acc_members', ['club_id' => $club->id, 'first_name' => 'Jo', 'user_id' => null]);
        $this->assertDatabaseHas('accounting_contacts', ['club_id' => $club->id, 'name' => 'Jo Blogs', 'user_id' => null]);
        // Nobody else is touched.
        $this->assertDatabaseHas('club_user', ['user_id' => $bystander->id, 'club_id' => $other->id]);
        $this->assertNotSame('Deleted user', $bystander->fresh()->name);
    }

    public function test_the_purge_skips_someone_who_became_a_clubs_only_owner_during_the_wait(): void
    {
        $club = Club::factory()->create();
        $owner = $this->makeClubAdmin($this->user(), $club, 'owner');
        $owner->forceFill(['closure_requested_at' => now()->subDays(40)])->save();

        $this->artisan('accounts:purge-closed')->assertSuccessful();

        $this->assertNull($owner->fresh()->purged_at);
        $this->assertSame($owner->name, $owner->fresh()->name);
    }

    public function test_closing_the_account_is_offered_on_the_profile_page_and_the_old_privacy_page_goes_there(): void
    {
        $user = $this->user();
        $user->forceFill(['closure_requested_at' => now()])->save();

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertInertia(fn ($page) => $page->component('Admin/Profile/Edit')->where('closureRequestedAt', $user->closure_requested_at->toIso8601String())->has('purgeDate'));
        $this->actingAs($user)->get('/members/privacy')->assertRedirect('/members/profile');
    }
}
