<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Models\Member;
use App\Mail\PassedOnMail;
use App\Models\Club;
use App\Models\Lodge;
use App\Models\MasonicHall;
use App\Models\PassedOnDelivery;
use App\Models\PassedOnItem;
use App\Models\User;
use App\Notifications\ClubNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PassOnSendTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private MasonicHall $hall;

    private Lodge $sister;

    private User $secretary;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();
        Storage::fake('local');
        $this->hall = MasonicHall::factory()->create();
        $this->club = $this->lodgeInHall();
        $this->sister = Lodge::factory()->create(['masonic_hall_id' => $this->hall->id]);
        $this->secretary = $this->member($this->club, 'secretary');
    }

    private function lodgeInHall(): Club
    {
        $club = Club::factory()->create();
        Lodge::factory()->create(['club_id' => $club->id, 'masonic_hall_id' => $this->hall->id]);

        return $club;
    }

    /** @param  array<string, mixed>|null  $prefs */
    private function member(Club $club, string $role = 'member', ?array $prefs = null): User
    {
        $user = User::factory()->create(['passed_on_prefs' => $prefs]);
        $club->users()->attach($user->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    /** @param  array<string, mixed>  $overrides */
    private function passOn(array $overrides = [], ?User $as = null, ?Club $club = null): TestResponse
    {
        return $this->actingAs($as ?? $this->secretary)->post(route('member.pass_on.store', ($club ?? $this->club)->slug), $overrides + [
            'kind' => 'sister',
            'type' => 'summons',
            'lodge_slug' => $this->sister->slug,
            'title' => 'Regular meeting',
            'body' => '<p>All brethren welcome.</p>',
            'meeting_date' => now()->addDays(20)->toDateString(),
            'meeting_time' => '18:30',
            'venue' => 'The Masonic Hall',
        ]);
    }

    public function test_a_secretary_passes_on_a_sister_lodge_summons_and_members_get_it_as_they_chose(): void
    {
        $both = $this->member($this->club);
        $emailOnly = $this->member($this->club, prefs: ['kinds' => ['sister' => ['email']]]);
        $off = $this->member($this->club, prefs: ['kinds' => ['sister' => []]]);

        $this->passOn(['attachment' => UploadedFile::fake()->create('summons.pdf', 120, 'application/pdf')])
            ->assertSessionHasNoErrors()->assertRedirect(route('member.pass_on', $this->club->slug));

        $item = PassedOnItem::sole();
        $this->assertSame('summons:'.$this->sister->id.':'.now()->addDays(20)->toDateString(), $item->dedupe_key);
        $this->assertSame('Passed on by '.$this->secretary->name.' of '.$this->club->name, $item->passed_on_by);
        Storage::disk('local')->assertExists($item->attachment_path);
        $this->assertNotNull($item->sent_at);

        Notification::assertSentTo($both, ClubNotification::class, fn (ClubNotification $notice) => $notice->category === ClubNotification::PASSED_ON && $notice->title === 'Regular meeting');
        Notification::assertNotSentTo([$emailOnly, $off], ClubNotification::class);
        Mail::assertQueued(PassedOnMail::class, fn (PassedOnMail $mail) => $mail->hasTo($both->email));
        Mail::assertQueued(PassedOnMail::class, fn (PassedOnMail $mail) => $mail->hasTo($emailOnly->email));
        Mail::assertNotQueued(PassedOnMail::class, fn (PassedOnMail $mail) => $mail->hasTo($off->email));
    }

    public function test_roster_members_without_an_account_are_emailed(): void
    {
        $member = Member::create(['club_id' => $this->club->id, 'first_name' => 'Ron', 'last_name' => 'Hill', 'email' => 'ron@example.test', 'membership_status' => 'active']);

        $this->passOn(['kind' => 'provincial'])->assertSessionHasNoErrors();

        $this->assertSame('notice', PassedOnItem::sole()->type);
        $this->assertNull(PassedOnItem::sole()->lodge_id);
        $this->assertTrue(PassedOnDelivery::where('member_id', $member->id)->where('emailed', true)->exists());
        Mail::assertQueued(PassedOnMail::class, fn (PassedOnMail $mail) => $mail->hasTo('ron@example.test'));
    }

    public function test_followers_of_the_lodge_get_the_summons_in_the_app_but_not_by_email(): void
    {
        $follower = User::factory()->create();
        $follower->followedLodges()->attach($this->sister->id, ['in_calendar' => true, 'notify_summons' => true]);
        $quiet = User::factory()->create();
        $quiet->followedLodges()->attach($this->sister->id, ['in_calendar' => true, 'notify_summons' => false]);

        $this->passOn()->assertSessionHasNoErrors();

        Notification::assertSentTo($follower, ClubNotification::class);
        Mail::assertNotQueued(PassedOnMail::class, fn (PassedOnMail $mail) => $mail->hasTo($follower->email));
        Notification::assertNotSentTo($quiet, ClubNotification::class);
        $this->assertTrue(PassedOnDelivery::where('user_id', $follower->id)->value('follower'));
    }

    public function test_a_member_who_mutes_the_lodge_gets_nothing_about_it_even_as_a_follower(): void
    {
        $muted = $this->member($this->club, prefs: ['muted_lodges' => [$this->sister->id]]);
        $muted->followedLodges()->attach($this->sister->id, ['in_calendar' => true, 'notify_summons' => true]);

        $this->passOn()->assertSessionHasNoErrors();

        Notification::assertNotSentTo($muted, ClubNotification::class);
        Mail::assertNotQueued(PassedOnMail::class, fn (PassedOnMail $mail) => $mail->hasTo($muted->email));
    }

    public function test_two_secretaries_passing_on_the_same_meeting_reach_a_shared_member_once(): void
    {
        $other = $this->lodgeInHall();
        $otherSecretary = $this->member($other, 'secretary');
        $shared = $this->member($this->club);
        $other->users()->attach($shared->id, ['role' => 'member', 'status' => 'active']);

        $this->passOn()->assertSessionHasNoErrors();
        $this->passOn(as: $otherSecretary, club: $other)->assertSessionHasNoErrors();

        $this->assertSame(1, PassedOnDelivery::where('user_id', $shared->id)->count());
        Notification::assertSentToTimes($shared, ClubNotification::class, 1);
    }

    public function test_the_same_provincial_circular_from_two_lodges_reaches_a_person_once(): void
    {
        $other = $this->lodgeInHall();
        $otherSecretary = $this->member($other, 'secretary');
        $shared = $this->member($this->club);
        $other->users()->attach($shared->id, ['role' => 'member', 'status' => 'active']);
        $circular = fn () => ['kind' => 'provincial', 'title' => 'Provincial newsletter', 'body' => null, 'attachment' => UploadedFile::fake()->createWithContent('circular.pdf', '%PDF-1.4 the same circular')];

        $this->passOn($circular())->assertSessionHasNoErrors();
        $this->passOn(['title' => 'Province: newsletter'] + $circular(), as: $otherSecretary, club: $other)->assertSessionHasNoErrors();

        $this->assertSame(1, PassedOnDelivery::where('user_id', $shared->id)->count());
    }

    public function test_only_lodges_on_the_list_can_be_chosen(): void
    {
        $stranger = Lodge::factory()->create();

        $this->passOn(['lodge_slug' => $stranger->slug])->assertSessionHasErrors('lodge_slug');
        $this->passOn(['meeting_date' => null])->assertSessionHasErrors('meeting_date');
        $this->assertSame(0, PassedOnItem::count());
    }

    public function test_a_plain_member_cannot_pass_anything_on(): void
    {
        $member = $this->member($this->club);

        $this->passOn(as: $member)->assertForbidden();
        $this->actingAs($member)->get(route('member.pass_on', $this->club->slug))->assertForbidden();
        $this->assertSame(0, PassedOnItem::count());
    }

    public function test_the_note_is_sanitised(): void
    {
        $this->passOn(['body' => '<p>Hello</p><script>alert(1)</script>'])->assertSessionHasNoErrors();

        $this->assertStringNotContainsString('<script', (string) PassedOnItem::sole()->body);
    }

    public function test_pasted_text_keeps_its_paragraphs_and_line_breaks(): void
    {
        $this->passOn(['body' => "Dear Brethren,\n\nThe meeting starts at 6pm.\nDining follows. Fish & chips."])->assertSessionHasNoErrors();

        $body = (string) PassedOnItem::sole()->body;
        $this->assertStringStartsWith('<p>Dear Brethren,</p><p>The meeting starts at 6pm.<br', $body);
        $this->assertStringEndsWith('Dining follows. Fish &amp; chips.</p>', $body);
    }

    public function test_followers_are_not_counted_so_a_secretary_cannot_work_out_how_many_there_are(): void
    {
        $this->member($this->club);
        foreach (range(1, 3) as $ignored) {
            User::factory()->create()->followedLodges()->attach($this->sister->id, ['in_calendar' => true, 'notify_summons' => true]);
        }

        $this->passOn()->assertSessionHas('success', 'Passed on. It is going to 2 people.');
        $this->assertSame(2, PassedOnItem::sole()->recipient_count);
    }

    public function test_followers_of_a_lodge_on_the_platform_get_nothing_passed_on_by_hand(): void
    {
        $theirs = Club::factory()->create(['settings' => ['summons_visibility' => 'members']]);
        $this->sister->forceFill(['club_id' => $theirs->id])->save();
        $follower = User::factory()->create();
        $follower->followedLodges()->attach($this->sister->id, ['in_calendar' => true, 'notify_summons' => true]);

        $this->passOn(['attachment' => UploadedFile::fake()->createWithContent('summons.pdf', '%PDF-1.4 private summons')])->assertSessionHasNoErrors();

        $this->assertFalse(PassedOnDelivery::where('user_id', $follower->id)->exists());
        Notification::assertNotSentTo($follower, ClubNotification::class);
    }

    public function test_the_same_address_is_emailed_once_however_many_rolls_it_is_on(): void
    {
        $other = $this->lodgeInHall();
        $otherSecretary = $this->member($other, 'secretary');
        $tom = $this->member($this->club);
        Member::create(['club_id' => $other->id, 'first_name' => 'Tom', 'last_name' => 'Roll', 'email' => strtoupper($tom->email), 'membership_status' => 'active']);
        Member::create(['club_id' => $this->club->id, 'first_name' => 'Ron', 'last_name' => 'Hill', 'email' => 'ron@example.test', 'membership_status' => 'active']);
        Member::create(['club_id' => $other->id, 'first_name' => 'Ron', 'last_name' => 'Hill', 'email' => 'Ron@example.test', 'membership_status' => 'active']);

        $this->passOn()->assertSessionHasNoErrors();
        $this->passOn(as: $otherSecretary, club: $other)->assertSessionHasNoErrors();

        Mail::assertQueued(PassedOnMail::class, 1 + 1 + 1 + 1); // the two Secretaries, Tom and Ron: once each
        Mail::assertQueuedCount(4);
    }

    public function test_former_members_on_the_roll_are_not_emailed(): void
    {
        Member::create(['club_id' => $this->club->id, 'first_name' => 'Old', 'last_name' => 'Timer', 'email' => 'old@example.test', 'membership_status' => 'historical']);

        $this->passOn()->assertSessionHasNoErrors();

        Mail::assertNotQueued(PassedOnMail::class, fn (PassedOnMail $mail) => $mail->hasTo('old@example.test'));
    }

    public function test_the_page_lists_what_was_sent_with_its_counts(): void
    {
        $this->member($this->club);
        $this->passOn();

        $this->actingAs($this->secretary)->get(route('member.pass_on', $this->club->slug))
            ->assertInertia(fn ($page) => $page->component('Member/PassOn/Index')
                ->where('sent.0.title', 'Regular meeting')
                ->where('sent.0.recipients', 2)
                ->has('lodges.sister', 1));
    }
}
