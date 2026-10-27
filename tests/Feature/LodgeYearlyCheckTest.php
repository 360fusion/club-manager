<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Lodge;
use App\Models\User;
use App\Notifications\LodgeClaimNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LodgeYearlyCheckTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Lodge $lodge;

    private User $owner;

    private User $secretary;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create();
        $this->lodge = Lodge::factory()->create(['club_id' => $this->club->id, 'claimed_at' => now()->subMonths(13), 'meets_text' => 'Third Monday, September to May']);
        $this->owner = $this->makeClubAdmin(User::factory()->create(), $this->club, 'owner');
        $this->secretary = $this->makeClubAdmin(User::factory()->create(), $this->club, 'coach');
        $this->member = $this->makeClubAdmin(User::factory()->create(), $this->club, 'member');
    }

    public function test_the_lodges_officers_are_asked_once_a_year_to_check_the_listing(): void
    {
        Notification::fake();

        $this->artisan('lodges:remind-secretaries')->assertSuccessful();
        $this->artisan('lodges:remind-secretaries')->assertSuccessful();

        Notification::assertSentToTimes($this->owner, LodgeClaimNotification::class, 1);
        Notification::assertSentTo($this->secretary, LodgeClaimNotification::class, fn (LodgeClaimNotification $notice) => str_contains($notice->body, 'Third Monday, September to May'));
        Notification::assertNotSentTo($this->member, LodgeClaimNotification::class);
        $this->assertNotNull($this->lodge->fresh()->secretary_reminded_at);
    }

    public function test_a_lodge_with_one_admin_is_asked_to_add_a_second(): void
    {
        Notification::fake();

        $this->artisan('lodges:remind-secretaries');

        Notification::assertSentTo($this->owner, LodgeClaimNotification::class, fn (LodgeClaimNotification $notice) => str_contains($notice->body, 'please add a second'));
    }

    public function test_a_lodge_with_two_admins_is_not_asked_to_add_another(): void
    {
        Notification::fake();
        $this->makeClubAdmin(User::factory()->create(), $this->club, 'admin');

        $this->artisan('lodges:remind-secretaries');

        Notification::assertSentTo($this->owner, LodgeClaimNotification::class, fn (LodgeClaimNotification $notice) => ! str_contains($notice->body, 'please add a second'));
    }

    public function test_a_recently_claimed_confirmed_or_unmanaged_lodge_is_not_asked(): void
    {
        Notification::fake();
        $this->lodge->update(['last_verified_at' => now()->subMonths(2)]);
        Lodge::factory()->create(['club_id' => Club::factory()->create()->id, 'claimed_at' => now()->subMonth()]);
        Lodge::factory()->create();

        $this->artisan('lodges:remind-secretaries')->expectsOutput('Asked 0 lodge(s) to check their listing.');

        Notification::assertNothingSent();
    }

    public function test_the_secretary_confirms_the_listing_but_a_plain_member_cannot(): void
    {
        $this->actingAs($this->member)->post(route('lodges.listing_checked', $this->lodge->slug))->assertForbidden();
        $this->assertNull($this->lodge->fresh()->last_verified_at);

        $this->actingAs($this->secretary)->post(route('lodges.listing_checked', $this->lodge->slug))->assertSessionHasNoErrors();
        $this->assertTrue($this->lodge->fresh()->last_verified_at->isToday());
    }
}
