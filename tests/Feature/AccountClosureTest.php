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

    public function test_the_data_download_needs_the_password_and_never_contains_secrets(): void
    {
        $club = Club::factory()->create(['name' => 'Export Lodge']);
        $user = $this->makeClubAdmin($this->user(), $club, 'member');
        $user->forceFill(['two_factor_secret' => encrypt('SECRETSECRET'), 'calendar_token' => 'calendar-secret'])->save();
        $user->createToken('laptop');

        $this->actingAs($user)->post(route('account.privacy.export'), ['password' => 'wrong'])->assertSessionHasErrorsIn('export', 'password');

        $response = $this->actingAs($user)->post(route('account.privacy.export'), ['password' => 'right-password']);
        $response->assertOk()->assertDownload();
        $body = $response->streamedContent();
        $this->assertStringContainsString('Export Lodge', $body);
        $this->assertStringContainsString('laptop', $body);
        $this->assertStringNotContainsString('SECRETSECRET', $body);
        $this->assertStringNotContainsString('calendar-secret', $body);
        $this->assertStringNotContainsString($user->password, $body);
    }

    public function test_another_persons_data_is_not_in_the_download(): void
    {
        $club = Club::factory()->create();
        $me = $this->makeClubAdmin($this->user(), $club, 'member');
        $them = $this->makeClubAdmin(User::factory()->create(['email' => 'them@example.test']), $club, 'member');
        DB::table('club_acc_members')->insert(['club_id' => $club->id, 'user_id' => $them->id, 'first_name' => 'Zed', 'last_name' => 'Other', 'created_at' => now(), 'updated_at' => now()]);

        $body = $this->actingAs($me)->post(route('account.privacy.export'), ['password' => 'right-password'])->streamedContent();

        $this->assertStringNotContainsString('them@example.test', $body);
        $this->assertStringNotContainsString('Zed', $body);
    }

    public function test_the_page_is_for_signed_in_people_only(): void
    {
        $this->get(route('account.privacy'))->assertRedirect();
        $this->actingAs($this->user())->get(route('account.privacy'))->assertOk();
    }
}
