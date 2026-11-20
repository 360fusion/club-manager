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
