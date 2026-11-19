<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Models\Member;
use App\Mail\LodgeClaimMail;
use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MemberOwnDetailsTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $secretary;

    private User $tom;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->club = Club::factory()->create(['email' => null]);
        $this->secretary = User::factory()->create(['name' => 'Sam Secretary', 'email' => 'secretary@example.test']);
        $this->club->users()->attach($this->secretary->id, ['role' => 'coach', 'status' => 'active']);
        $this->tom = User::factory()->create(['name' => 'Tom Hart', 'email' => 'tom@example.test']);
        $this->club->users()->attach($this->tom->id, ['role' => 'member', 'status' => 'active', 'phone' => '01234 111111']);
        Member::create(['club_id' => $this->club->id, 'user_id' => $this->tom->id, 'first_name' => 'Tom', 'last_name' => 'Hart', 'membership_status' => 'active', 'address_line_1' => '1 Old Road', 'city' => 'Bath']);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function save(User $user, array $changes)
    {
        return $this->actingAs($user)->post(route('member.profile.update', ['slug' => $this->club->slug]), $changes + ['name' => $user->name, 'email' => $user->email, 'phone' => '01234 111111']);
    }

    public function test_a_member_updates_their_address_and_phone_on_the_roster_and_the_secretary_is_told(): void
    {
        $this->save($this->tom, ['phone' => '07700 900123', 'address_line_1' => '2 New Street', 'city' => 'Bath', 'postcode' => 'BA1 1AA'])->assertSessionHasNoErrors();

        $roster = Member::where('user_id', $this->tom->id)->sole();
        $this->assertSame(['2 New Street', 'BA1 1AA', '07700 900123'], [$roster->address_line_1, $roster->postcode, $roster->phone]);

        Mail::assertQueued(LodgeClaimMail::class, fn (LodgeClaimMail $mail) => $mail->hasTo('secretary@example.test')
            && str_contains($mail->body, 'Phone: 01234 111111 → 07700 900123')
            && str_contains($mail->body, 'Address: 1 Old Road, Bath → 2 New Street, Bath, BA1 1AA')
            && ! str_contains($mail->body, 'Emergency contact'));
    }

    public function test_the_secretary_is_not_emailed_when_nothing_on_the_record_changed_or_about_their_own_details(): void
    {
        $this->save($this->tom, ['address_line_1' => '1 Old Road', 'city' => 'Bath', 'dietary_notes' => 'Vegetarian'])->assertSessionHasNoErrors();
        $this->save($this->secretary, ['phone' => '07000 000000'])->assertSessionHasNoErrors();

        Mail::assertNothingQueued();
    }

    public function test_the_address_only_shows_for_a_member_on_the_roster(): void
    {
        $this->actingAs($this->tom)->get(route('member.profile', ['slug' => $this->club->slug]))
            ->assertInertia(fn ($page) => $page->where('memberProfile.address.address_line_1', '1 Old Road'));
        $this->actingAs($this->secretary)->get(route('member.profile', ['slug' => $this->club->slug]))
            ->assertInertia(fn ($page) => $page->where('memberProfile.address', null));
    }
}
