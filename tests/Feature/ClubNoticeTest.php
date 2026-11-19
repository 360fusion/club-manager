<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Models\Member;
use App\Mail\LodgeClaimMail;
use App\Models\Club;
use App\Models\ClubNotice;
use App\Models\Meeting;
use App\Models\PushDevice;
use App\Models\User;
use App\Notifications\ClubNotification;
use App\Services\Meetings\MeetingReplies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClubNoticeTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $secretary;

    private User $diner;

    private User $silent;

    private User $visitor;

    private User $outsider;

    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();

        $this->club = Club::factory()->create();
        $this->secretary = $this->person('coach');
        $this->diner = $this->person('member');
        $this->silent = $this->person('member');
        $this->visitor = $this->person('visitor');

        $other = Club::factory()->create();
        $this->outsider = User::factory()->create();
        $other->users()->attach($this->outsider->id, ['role' => 'member', 'status' => 'active']);

        $this->meeting = Meeting::create(['club_id' => $this->club->id, 'title' => 'December Meeting', 'meeting_date' => now()->addDays(5)->toDateString(), 'starts_at' => '18:30:00', 'venue' => 'Hall', 'dress_code' => 'Suit', 'status' => 'published']);
        $this->reply($this->secretary, 'apologies');
        $this->reply($this->diner, 'attending_dining');
        $this->reply($this->outsider, 'attending_dining');
    }

    private function person(string $role): User
    {
        $user = User::factory()->create();
        $this->club->users()->attach($user->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    private function reply(User $user, string $status): void
    {
        app(MeetingReplies::class)->rsvpFor($this->meeting, $user)->update(['attendance_status' => $status, 'responded_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function send(array $overrides = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->secretary)->post(route('admin.notices.store', ['clubSlug' => $this->club->slug]), $overrides + [
            'audience' => 'all', 'title' => 'Dining starts 8pm', 'body' => 'The ceremony is running long.', 'push' => true, 'email' => true,
        ]);
    }

    /**
     * @param  list<User>  $expected
     */
    private function assertNotified(array $expected): void
    {
        $everyone = [$this->secretary, $this->diner, $this->silent, $this->visitor, $this->outsider];

        foreach ($everyone as $user) {
            in_array($user, $expected, true)
                ? Notification::assertSentTo($user, ClubNotification::class, fn (ClubNotification $n) => $n->category === ClubNotification::NOTICE && $n->title === 'Dining starts 8pm')
                : Notification::assertNotSentTo($user, ClubNotification::class);
        }
    }

    public function test_all_members_reaches_this_lodges_members_only_and_emails_those_without_the_app(): void
    {
        PushDevice::factory()->create(['user_id' => $this->diner->id]);

        $this->send()->assertRedirect(route('admin.notices.index', ['clubSlug' => $this->club->slug]));

        $this->assertNotified([$this->secretary, $this->diner, $this->silent]);
        Mail::assertQueued(LodgeClaimMail::class, 2);
        Mail::assertQueued(LodgeClaimMail::class, fn (LodgeClaimMail $mail) => $mail->hasTo($this->silent->email) && $mail->body === 'The ceremony is running long.');
        Mail::assertNotQueued(LodgeClaimMail::class, fn (LodgeClaimMail $mail) => $mail->hasTo($this->diner->email));

        $notice = ClubNotice::sole();
        $this->assertSame([3, 1, 2], [$notice->recipient_count, $notice->push_count, $notice->email_count]);
        $this->assertNotNull($notice->sent_at);
    }

    public function test_someone_who_muted_notices_on_their_phone_gets_the_email(): void
    {
        PushDevice::factory()->create(['user_id' => $this->diner->id]);
        $this->diner->forceFill(['push_muted' => [ClubNotification::NOTICE]])->save();

        $this->send();

        Mail::assertQueued(LodgeClaimMail::class, fn (LodgeClaimMail $mail) => $mail->hasTo($this->diner->email));
    }

    public function test_diners_are_those_dining_at_the_next_meeting_in_this_lodge(): void
    {
        $this->send(['audience' => 'diners']);

        $this->assertNotified([$this->diner]);
        $this->assertSame($this->meeting->id, ClubNotice::sole()->meeting_id);
    }

    public function test_not_yet_replied_leaves_out_those_who_answered_and_visitors(): void
    {
        $this->send(['audience' => 'not_replied']);

        $this->assertNotified([$this->silent]);
    }

    public function test_officers_are_members_holding_an_office(): void
    {
        Member::create(['club_id' => $this->club->id, 'user_id' => $this->silent->id, 'first_name' => 'Sam', 'last_name' => 'Silent', 'membership_status' => 'active', 'current_office' => LodgeOffice::Secretary->value]);
        Member::create(['club_id' => $this->club->id, 'user_id' => $this->diner->id, 'first_name' => 'Dan', 'last_name' => 'Diner', 'membership_status' => 'active']);

        $this->send(['audience' => 'officers']);

        $this->assertNotified([$this->silent]);
    }

    public function test_with_phone_notifications_off_everyone_gets_an_email_and_with_email_off_nobody_does(): void
    {
        PushDevice::factory()->create(['user_id' => $this->diner->id]);

        $this->send(['push' => false]);
        Notification::assertNothingSent();
        Mail::assertQueued(LodgeClaimMail::class, 3);

        $this->send(['push' => true, 'email' => false, 'title' => 'Second']);
        Mail::assertQueued(LodgeClaimMail::class, 3);
        Notification::assertSentTo($this->diner, ClubNotification::class);
    }

    public function test_a_notice_needs_a_channel_and_someone_to_go_to(): void
    {
        $this->send(['push' => false, 'email' => false])->assertSessionHasErrors('push');

        $this->meeting->update(['status' => 'draft']);
        $this->send(['audience' => 'diners'])->assertSessionHasErrors('audience');

        $this->assertSame(0, ClubNotice::count());
    }

    public function test_only_staff_who_send_newsletters_can_open_or_send(): void
    {
        $this->actingAs($this->silent)->get(route('admin.notices.index', ['clubSlug' => $this->club->slug]))->assertForbidden();
        $this->send([], $this->silent)->assertForbidden();
        $this->send([], $this->outsider)->assertForbidden();
        $this->assertSame(0, ClubNotice::count());

        $this->actingAs($this->secretary)->get(route('admin.notices.index', ['clubSlug' => $this->club->slug]))
            ->assertInertia(fn ($page) => $page->component('Admin/Notices/Index')
                ->where('counts', ['all' => 3, 'diners' => 1, 'not_replied' => 1, 'officers' => 0])
                ->where('meeting.id', $this->meeting->id));
    }
}
