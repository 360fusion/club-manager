<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubVisitRequest;
use App\Models\Lodge;
use App\Models\User;
use App\Notifications\LodgeClaimNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LodgeVisitRequestTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Lodge $lodge;

    private User $secretary;

    private User $member;

    private User $visitor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create();
        $this->lodge = Lodge::factory()->create(['club_id' => $this->club->id]);
        $this->secretary = $this->makeClubAdmin(User::factory()->create(), $this->club, 'coach');
        $this->member = $this->makeClubAdmin(User::factory()->create(), $this->club, 'member');
        $this->visitor = User::factory()->create(['name' => 'Vic Visitor']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function askToVisit(?User $user = null, array $overrides = [])
    {
        return $this->actingAs($user ?? $this->visitor)->post(route('lodges.visits.store', $this->lodge->slug), [
            'meeting_date' => now()->addWeeks(3)->toDateString(),
            'home_lodge_name' => 'Lodge of Fraternity',
            'home_lodge_number' => '4800',
            'rank' => 'WBro',
            'dining' => true,
            'message' => 'Travelling up for work.',
            ...$overrides,
        ]);
    }

    public function test_a_visitor_asks_to_visit_and_the_secretary_is_told(): void
    {
        Notification::fake();

        $this->askToVisit()->assertSessionHasNoErrors();

        $visit = ClubVisitRequest::sole();
        $this->assertSame(ClubVisitRequest::PENDING, $visit->status);
        $this->assertTrue($visit->dining);
        Notification::assertSentTo($this->secretary, LodgeClaimNotification::class, fn (LodgeClaimNotification $notice) => str_contains($notice->title, 'Vic Visitor asks to visit') && in_array('mail', $notice->via($this->secretary), true));
        Notification::assertNotSentTo($this->member, LodgeClaimNotification::class);
    }

    public function test_the_date_must_be_within_the_next_year_and_not_asked_twice(): void
    {
        $this->askToVisit(overrides: ['meeting_date' => now()->subDay()->toDateString()])->assertSessionHasErrors(['meeting_date' => 'Choose a meeting date in the future.']);
        $this->askToVisit(overrides: ['meeting_date' => now()->addYears(2)->toDateString()])->assertSessionHasErrors(['meeting_date' => 'Choose a date within the next year.']);

        $this->askToVisit()->assertSessionHasNoErrors();
        $this->askToVisit()->assertSessionHasErrors(['visit' => 'You have already asked to visit on that date.']);

        $this->assertSame(1, ClubVisitRequest::count());
    }

    public function test_a_member_and_a_lodge_not_on_the_platform_cannot_be_asked(): void
    {
        $this->askToVisit($this->member)->assertSessionHasErrors(['visit' => 'You are already a member of this lodge.']);

        $unmanaged = Lodge::factory()->create();
        $this->actingAs($this->visitor)->post(route('lodges.visits.store', $unmanaged->slug), ['meeting_date' => now()->addWeek()->toDateString(), 'home_lodge_name' => 'X'])->assertNotFound();

        $this->assertSame(0, ClubVisitRequest::count());
    }

    public function test_the_secretary_accepts_with_a_reply_and_the_visitor_is_told(): void
    {
        $this->askToVisit();
        Notification::fake();
        $visit = ClubVisitRequest::sole();

        $this->actingAs($this->secretary)
            ->post(route('admin.visitors.visits.answer', ['clubSlug' => $this->club->slug, 'id' => $visit->id]), ['accept' => true, 'reply' => 'Tyled at 6.30pm.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ClubVisitRequest::ACCEPTED, $visit->fresh()->status);
        $this->assertSame($this->secretary->id, $visit->fresh()->decided_by);
        Notification::assertSentTo($this->visitor, LodgeClaimNotification::class, fn (LodgeClaimNotification $notice) => $notice->body === 'Tyled at 6.30pm.');

        $this->actingAs($this->secretary)
            ->post(route('admin.visitors.visits.answer', ['clubSlug' => $this->club->slug, 'id' => $visit->id]), ['accept' => false])
            ->assertSessionHasErrors(['visit' => 'That request has already been answered.']);
    }

    public function test_a_plain_member_or_another_lodges_admin_cannot_answer(): void
    {
        $this->askToVisit();
        $visit = ClubVisitRequest::sole();
        $otherAdmin = $this->makeClubAdmin(User::factory()->create(), $otherClub = Club::factory()->create(), 'admin');

        $this->actingAs($this->member)->post(route('admin.visitors.visits.answer', ['clubSlug' => $this->club->slug, 'id' => $visit->id]), ['accept' => true])->assertForbidden();
        $this->actingAs($otherAdmin)->post(route('admin.visitors.visits.answer', ['clubSlug' => $otherClub->slug, 'id' => $visit->id]), ['accept' => true])->assertNotFound();

        $this->assertSame(ClubVisitRequest::PENDING, $visit->fresh()->status);
    }

    public function test_the_visitor_sees_and_withdraws_a_waiting_request_on_the_lodge_page(): void
    {
        $this->askToVisit();
        $visit = ClubVisitRequest::sole();

        $this->actingAs($this->visitor)->get(route('lodges.show', $this->lodge->slug))
            ->assertInertia(fn (Assert $page) => $page->where('lodge.visitor.can_visit', true)->where('lodge.visitor.visits.0.status', 'pending'));

        $this->actingAs(User::factory()->create())->delete(route('lodges.visits.withdraw', ['slug' => $this->lodge->slug, 'id' => $visit->id]))->assertNotFound();
        $this->actingAs($this->visitor)->delete(route('lodges.visits.withdraw', ['slug' => $this->lodge->slug, 'id' => $visit->id]));

        $this->assertSame(0, ClubVisitRequest::count());
    }
}
