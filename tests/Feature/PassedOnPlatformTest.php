<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Lodge;
use App\Models\MasonicHall;
use App\Models\Meeting;
use App\Models\PassedOnDelivery;
use App\Models\PassedOnItem;
use App\Models\User;
use App\Notifications\ClubNotification;
use App\Services\PassedOn\PlatformSummons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PassedOnPlatformTest extends TestCase
{
    use RefreshDatabase;

    private Club $ours;

    private Club $theirs;

    private Lodge $theirLodge;

    private User $ourMember;

    private User $ourSecretary;

    private User $theirSecretary;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();
        Storage::fake('local');
        $hall = MasonicHall::factory()->create();
        $this->ours = Club::factory()->create();
        Lodge::factory()->create(['club_id' => $this->ours->id, 'masonic_hall_id' => $hall->id]);
        $this->theirs = Club::factory()->create(['settings' => ['summons_visibility' => 'public_safe']]);
        $this->theirLodge = Lodge::factory()->create(['club_id' => $this->theirs->id, 'masonic_hall_id' => $hall->id]);

        $this->ourMember = $this->join($this->ours, 'member');
        $this->ourSecretary = $this->join($this->ours, 'secretary');
        $this->theirSecretary = $this->join($this->theirs, 'secretary');
    }

    private function join(Club $club, string $role): User
    {
        $user = User::factory()->create();
        $club->users()->attach($user->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    private function publishTheirMeeting(int $inDays = 14): Meeting
    {
        $meeting = Meeting::create(['club_id' => $this->theirs->id, 'title' => 'Regular meeting', 'meeting_date' => now()->addDays($inDays)->toDateString(), 'starts_at' => '18:30', 'venue' => 'The Masonic Hall', 'dress_code' => 'Dark suit', 'rsvp_cutoff_at' => now()->addDays(10), 'status' => 'draft']);
        $this->actingAs($this->theirSecretary)->post(route('admin.meetings.publish', [$this->theirs->slug, $meeting->id]))->assertSessionHasNoErrors();

        return $meeting;
    }

    public function test_a_platform_lodges_summons_reaches_members_of_lodges_that_list_it_with_only_the_key_facts(): void
    {
        $this->publishTheirMeeting();

        $copy = PassedOnDelivery::where('user_id', $this->ourMember->id)->sole();
        $this->assertSame('sister', $copy->kind);
        $this->assertSame(PassedOnItem::PLATFORM, $copy->item->source);
        $this->assertNull($copy->item->body);
        $this->assertNull($copy->item->attachment_path);
        $this->assertSame('The Masonic Hall', $copy->item->venue);
        $this->assertSame('Published by '.$this->theirs->name, $copy->item->passed_on_by);
        $this->assertFalse(PassedOnDelivery::where('user_id', $this->theirSecretary->id)->exists());
    }

    public function test_a_members_only_lodge_sends_nothing_outside_and_can_be_passed_on_by_hand(): void
    {
        $this->theirs->update(['settings' => ['summons_visibility' => 'members']]);
        $this->publishTheirMeeting();
        $this->assertSame(0, PassedOnDelivery::count());

        $this->actingAs($this->ourSecretary)->post(route('member.pass_on.store', $this->ours->slug), [
            'kind' => 'sister', 'type' => 'summons', 'lodge_slug' => $this->theirLodge->slug, 'title' => 'Their meeting', 'body' => 'Hello', 'meeting_date' => now()->addDays(14)->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertTrue(PassedOnDelivery::where('user_id', $this->ourMember->id)->exists());
    }

    public function test_a_secretary_attaching_the_pdf_to_a_platform_summons_sends_no_second_notice(): void
    {
        $this->publishTheirMeeting();
        Notification::assertSentToTimes($this->ourMember, ClubNotification::class, 1);

        $this->actingAs($this->ourSecretary)->post(route('member.pass_on.store', $this->ours->slug), [
            'kind' => 'sister', 'type' => 'summons', 'lodge_slug' => $this->theirLodge->slug, 'title' => 'Regular meeting',
            'meeting_date' => now()->addDays(14)->toDateString(), 'attachment' => UploadedFile::fake()->createWithContent('full.pdf', '%PDF-1.4 full summons'),
        ])->assertSessionHasNoErrors();

        $copy = PassedOnDelivery::where('user_id', $this->ourMember->id)->sole();
        $this->assertNotNull($copy->item->attachment_path);
        $this->assertSame(PassedOnItem::MANUAL, $copy->item->source);
        Notification::assertSentToTimes($this->ourMember, ClubNotification::class, 1);
    }

    public function test_a_follower_who_is_also_in_a_listing_lodge_is_told_once(): void
    {
        $this->ourMember->followedLodges()->attach($this->theirLodge->id, ['in_calendar' => true, 'notify_summons' => true]);
        $outsider = User::factory()->create();
        $outsider->followedLodges()->attach($this->theirLodge->id, ['in_calendar' => true, 'notify_summons' => true]);

        $this->publishTheirMeeting();

        Notification::assertSentToTimes($this->ourMember, ClubNotification::class, 1);
        Notification::assertSentTo($this->ourMember, ClubNotification::class, fn (ClubNotification $notice) => $notice->category === ClubNotification::PASSED_ON);
        Notification::assertSentTo($outsider, ClubNotification::class, fn (ClubNotification $notice) => $notice->category === ClubNotification::SUMMONS);
    }

    public function test_a_member_of_the_lodge_itself_does_not_get_a_passed_on_copy_of_its_summons(): void
    {
        $this->theirs->update(['settings' => ['summons_visibility' => 'members']]);
        $this->theirs->users()->attach($this->ourMember->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($this->ourSecretary)->post(route('member.pass_on.store', $this->ours->slug), [
            'kind' => 'sister', 'type' => 'summons', 'lodge_slug' => $this->theirLodge->slug, 'title' => 'Their meeting', 'body' => 'Hello', 'meeting_date' => now()->addDays(14)->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertFalse(PassedOnDelivery::where('user_id', $this->ourMember->id)->exists());
    }

    public function test_a_lodge_that_only_shares_with_approved_visitors_is_passed_on_by_hand(): void
    {
        $this->theirs->update(['settings' => ['summons_visibility' => 'approved_visitors']]);
        $this->publishTheirMeeting();
        $this->assertSame(0, PassedOnDelivery::count());

        $this->actingAs($this->ourSecretary)->post(route('member.pass_on.store', $this->ours->slug), [
            'kind' => 'sister', 'type' => 'summons', 'lodge_slug' => $this->theirLodge->slug, 'title' => 'Their meeting', 'body' => 'Hello', 'meeting_date' => now()->addDays(14)->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertTrue(PassedOnDelivery::where('user_id', $this->ourMember->id)->exists());
    }

    public function test_a_follower_who_muted_the_lodge_is_not_told_it_published_a_summons(): void
    {
        $outsider = User::factory()->create(['passed_on_prefs' => ['muted_lodges' => [$this->theirLodge->id]]]);
        $outsider->followedLodges()->attach($this->theirLodge->id, ['in_calendar' => true, 'notify_summons' => true]);

        $this->publishTheirMeeting();

        Notification::assertNotSentTo($outsider, ClubNotification::class);
    }

    public function test_publishing_does_not_query_once_per_member_of_the_listing_lodges(): void
    {
        $count = function (int $inDays): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->publishTheirMeeting($inDays);
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $before = $count(14);
        foreach (range(1, 5) as $ignored) {
            $this->join($this->ours, 'member');
        }
        $after = $count(21);

        // Each extra member costs only the insert of their own copy.
        $this->assertLessThanOrEqual($before + 5, $after);
    }

    public function test_the_copy_carries_the_dining_details(): void
    {
        $meeting = Meeting::create(['club_id' => $this->theirs->id, 'title' => 'Regular meeting', 'meeting_date' => now()->addDays(14)->toDateString(), 'starts_at' => '18:30', 'venue' => 'The Masonic Hall', 'dress_code' => 'Dark suit', 'rsvp_cutoff_at' => now()->addDays(10), 'status' => 'draft', 'festive_board_theme' => 'Burns night', 'festive_board_menu' => 'Haggis, neeps and tatties', 'dining_cost_guest' => 32]);
        $this->actingAs($this->theirSecretary)->post(route('admin.meetings.publish', [$this->theirs->slug, $meeting->id]))->assertSessionHasNoErrors();

        $this->assertSame(['theme' => 'Burns night', 'menu' => 'Haggis, neeps and tatties', 'guest_cost' => '£32.00'], PassedOnItem::sole()->dining);
        $this->get(route('passed_on.public', PassedOnDelivery::where('user_id', $this->ourMember->id)->value('token')))->assertSee('Dining: Burns night')->assertSee('Guests: £32.00');
    }

    public function test_a_moved_meeting_updates_the_copy_and_tells_those_who_had_it(): void
    {
        $meeting = $this->publishTheirMeeting();
        $newDate = now()->addDays(21)->toDateString();

        $meeting->update(['meeting_date' => $newDate, 'venue' => 'The Old Hall']);

        $item = PassedOnItem::sole();
        $this->assertSame($newDate, $item->meeting_date->toDateString());
        $this->assertSame('The Old Hall', $item->venue);
        $this->assertSame('summons:'.$this->theirLodge->id.':'.$newDate, $item->dedupe_key);
        $this->assertSame([$item->dedupe_key], PassedOnDelivery::pluck('dedupe_key')->unique()->values()->all());
        Notification::assertSentTo($this->ourMember, ClubNotification::class, fn (ClubNotification $notice) => $notice->title === 'Changed: Regular meeting');

        // A change nobody visiting would notice (the minutes, say) tells nobody.
        $meeting->update(['rsvp_cutoff_at' => now()->addDays(15)]);
        Notification::assertSentToTimes($this->ourMember, ClubNotification::class, 2);
    }

    public function test_a_cancelled_or_deleted_meeting_marks_the_copy_cancelled_and_takes_it_out_of_the_calendar(): void
    {
        $meeting = $this->publishTheirMeeting();

        $meeting->update(['status' => 'cancelled']);

        $this->assertNotNull(PassedOnItem::sole()->cancelled_at);
        $copy = PassedOnDelivery::where('user_id', $this->ourMember->id)->sole();
        $this->get(route('passed_on.public', $copy->token))->assertSee('This meeting has been cancelled.');
        $this->actingAs($this->ourMember)->get(route('members.passed_on.show', $copy->id))->assertInertia(fn ($page) => $page->where('item.cancelled', true));
        Notification::assertSentTo($this->ourMember, ClubNotification::class, fn (ClubNotification $notice) => $notice->title === 'Cancelled: Regular meeting');
        $this->actingAs($this->ourMember)->get(route('members.calendar', ['month' => now()->addDays(14)->format('Y-m')]))
            ->assertInertia(fn ($page) => $page->where('items', fn ($items) => collect($items)->where('type', 'passed_on')->isEmpty()));

        $other = $this->publishTheirMeeting(28);
        $other->delete();
        $this->assertNotNull(PassedOnItem::where('dedupe_key', 'like', '%'.now()->addDays(28)->toDateString())->value('cancelled_at'));
    }

    public function test_publishing_still_works_when_passing_it_on_fails(): void
    {
        $outsider = User::factory()->create();
        $outsider->followedLodges()->attach($this->theirLodge->id, ['in_calendar' => true, 'notify_summons' => true]);
        $this->mock(PlatformSummons::class, function ($mock) {
            $mock->shouldReceive('publish')->andThrow(new \RuntimeException('Database went away'));
            $mock->shouldReceive('follow');
        });

        $meeting = Meeting::create(['club_id' => $this->theirs->id, 'title' => 'Regular meeting', 'meeting_date' => now()->addDays(14)->toDateString(), 'starts_at' => '18:30', 'venue' => 'Hall', 'dress_code' => 'Suit', 'rsvp_cutoff_at' => now()->addDays(10), 'status' => 'draft']);
        $this->actingAs($this->theirSecretary)->post(route('admin.meetings.publish', [$this->theirs->slug, $meeting->id]))
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        Notification::assertSentTo($outsider, ClubNotification::class, fn (ClubNotification $notice) => $notice->category === ClubNotification::SUMMONS);
    }

    public function test_without_a_pdf_the_form_refuses_a_summons_for_a_lodge_that_publishes_its_own(): void
    {
        $this->actingAs($this->ourSecretary)->post(route('member.pass_on.store', $this->ours->slug), [
            'kind' => 'sister', 'type' => 'summons', 'lodge_slug' => $this->theirLodge->slug, 'title' => 'Regular meeting', 'body' => 'Hello', 'meeting_date' => now()->addDays(14)->toDateString(),
        ])->assertSessionHasErrors(['lodge_slug' => $this->theirLodge->displayName().' publishes its own summonses on Club Manager, so your members already get them.']);

        $this->actingAs($this->ourSecretary)->post(route('member.pass_on.store', $this->ours->slug), [
            'kind' => 'sister', 'type' => 'notice', 'lodge_slug' => $this->theirLodge->slug, 'title' => 'Ladies night', 'body' => 'Tickets now',
        ])->assertSessionHasNoErrors();
    }
}
