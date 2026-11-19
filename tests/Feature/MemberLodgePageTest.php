<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\SubscriptionStatus;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The lodge page a member sees at /members/{slug}: organiser tools for staff, the officers, their subscription and the
 * lodge's members-only documents.
 */
class MemberLodgePageTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create();
    }

    private function person(ClubRole $role, ?LodgeOffice $office = null): User
    {
        $user = User::factory()->create();
        $this->club->users()->attach($user->id, ['role' => $role->value, 'status' => 'active']);
        Member::create(['club_id' => $this->club->id, 'user_id' => $user->id, 'first_name' => 'Pat', 'last_name' => $user->id, 'membership_status' => 'active', 'masonic_rank' => 'Bro', 'current_office' => ($office ?? LodgeOffice::Member)->value]);

        return $user;
    }

    private function lodgePage(User $user): AssertableInertia
    {
        $props = null;
        $this->actingAs($user)->get(route('member.dashboard', ['slug' => $this->club->slug]))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$props) {
                $props = $page->component('Member/Dashboard');
            });

        return $props;
    }

    public function test_a_member_sees_no_organiser_tools(): void
    {
        $this->lodgePage($this->person(ClubRole::Member))->where('organiserTools', null);
    }

    public function test_a_secretary_gets_the_tools_their_role_may_open_with_their_office(): void
    {
        $event = Event::create(['club_id' => $this->club->id, 'title' => 'Ladies festival', 'slug' => 'ladies-festival', 'starts_at' => now()->addDays(3), 'status' => 'upcoming']);
        $meeting = Meeting::create(['club_id' => $this->club->id, 'title' => 'December meeting', 'meeting_date' => now()->addDays(10)->toDateString(), 'starts_at' => '18:30:00', 'venue' => 'Hall', 'dress_code' => 'Suit', 'status' => 'published']);

        $this->lodgePage($this->person(ClubRole::Coach, LodgeOffice::Secretary))
            ->where('organiserTools.office', 'Secretary')
            ->where('organiserTools.links', [
                ['key' => 'event_night', 'label' => 'Event night', 'href' => route('admin.events.checkin', ['clubSlug' => $this->club->slug, 'id' => $event->id])],
                ['key' => 'catering', 'label' => 'Catering', 'href' => route('admin.meetings.dining', ['clubSlug' => $this->club->slug, 'id' => $meeting->id])],
                ['key' => 'add_booking', 'label' => 'Add booking', 'href' => route('admin.events.index', ['clubSlug' => $this->club->slug])],
                ['key' => 'send_notice', 'label' => 'Send notice', 'href' => route('admin.notices.index', ['clubSlug' => $this->club->slug])],
            ]);
    }

    public function test_a_treasurer_only_gets_the_event_tools_and_no_links_to_missing_events(): void
    {
        $this->lodgePage($this->person(ClubRole::Treasurer, LodgeOffice::Treasurer))
            ->where('organiserTools.links', [
                ['key' => 'add_booking', 'label' => 'Add booking', 'href' => route('admin.events.index', ['clubSlug' => $this->club->slug])],
            ]);
    }

    public function test_officers_are_listed_in_summons_order_with_their_rank_and_plain_members_left_out(): void
    {
        Member::create(['club_id' => $this->club->id, 'first_name' => 'Andrew', 'last_name' => 'Turner', 'membership_status' => 'active', 'masonic_rank' => 'WBro', 'current_office' => LodgeOffice::WorshipfulMaster->value]);
        Member::create(['club_id' => $this->club->id, 'first_name' => 'David', 'last_name' => 'Patel', 'membership_status' => 'active', 'masonic_rank' => 'WBro', 'provincial_rank' => 'PPrJGD', 'current_office' => LodgeOffice::Secretary->value]);

        $this->lodgePage($this->person(ClubRole::Member))
            ->where('officers', [
                ['role' => 'Worshipful Master', 'name' => 'WBro Andrew Turner'],
                ['role' => 'Secretary', 'name' => 'WBro David Patel PPrJGD'],
            ]);
    }

    public function test_the_subscription_is_the_latest_year_and_dues_owed_counts_every_year(): void
    {
        $user = $this->person(ClubRole::Member);
        $member = Member::where('user_id', $user->id)->first();
        MemberSubscription::create(['club_id' => $this->club->id, 'member_id' => $member->id, 'billing_year' => 2025, 'due_date' => '2025-10-01', 'amount_due' => 160, 'amount_paid' => 0, 'status' => SubscriptionStatus::Unpaid]);
        MemberSubscription::create(['club_id' => $this->club->id, 'member_id' => $member->id, 'billing_year' => 2026, 'due_date' => '2026-10-01', 'amount_due' => 185, 'amount_paid' => 185, 'status' => SubscriptionStatus::Paid]);

        $this->lodgePage($user)
            ->where('subscription', ['year' => 2026, 'amount_due' => 185, 'balance' => 0, 'status' => 'paid', 'due_date' => '1 October 2026'])
            ->where('duesOwed', 160);
    }

    public function test_documents_are_the_members_only_downloads_and_not_the_public_ones(): void
    {
        $downloads = fn (string $title, string $url, bool $membersOnly) => ['id' => $title, 'type' => 'downloads', 'members_only' => $membersOnly, 'items' => [['source' => 'link', 'title' => $title, 'url' => $url]]];
        Page::create(['club_id' => $this->club->id, 'title' => 'Documents', 'slug' => 'documents', 'is_published' => true, 'blocks' => [
            $downloads('Lodge by-laws', 'https://example.test/by-laws.pdf', true),
            $downloads('Visitor guide', 'https://example.test/visitors.pdf', false),
        ]]);

        $this->lodgePage($this->person(ClubRole::Member))
            ->where('documents', [['title' => 'Lodge by-laws', 'href' => 'https://example.test/by-laws.pdf', 'type' => 'Link']]);
    }
}
