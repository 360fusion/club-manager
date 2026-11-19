<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Lodge;
use App\Models\MemberVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberVisitTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $secretary;

    private User $tom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create();
        $this->secretary = User::factory()->create();
        $this->club->users()->attach($this->secretary->id, ['role' => 'coach', 'status' => 'active']);
        $this->tom = User::factory()->create(['name' => 'Tom Hart']);
        $this->club->users()->attach($this->tom->id, ['role' => 'member', 'status' => 'active']);
    }

    private function lodge(): Lodge
    {
        $type = ClubType::factory()->create(['code' => 'craft_lodge']);

        return Lodge::create(['club_type_id' => $type->id, 'name' => 'Unity Lodge', 'number' => '5', 'slug' => 'unity-lodge-5', 'status' => 'active']);
    }

    public function test_a_member_records_visits_to_a_directory_lodge_or_by_name_and_their_secretary_sees_where_but_not_the_note(): void
    {
        $lodge = $this->lodge();

        $this->actingAs($this->tom)->post(route('members.visits.store'), ['lodge_slug' => $lodge->slug, 'visited_on' => now()->subWeek()->toDateString(), 'note' => 'Dined, sat by the Tyler'])->assertSessionHasNoErrors();
        $this->actingAs($this->tom)->post(route('members.visits.store'), ['lodge_name' => 'St John No 1234', 'visited_on' => now()->subMonth()->toDateString()])->assertSessionHasNoErrors();

        $this->actingAs($this->tom)->get(route('members.lodges'))->assertInertia(fn ($page) => $page
            ->where('visits.0.lodge_slug', 'unity-lodge-5')
            ->where('visits.0.note', 'Dined, sat by the Tyler')
            ->where('visits.1.lodge', 'St John No 1234'));
        $this->actingAs($this->tom)->get(route('lodges.show', $lodge->slug))->assertInertia(fn ($page) => $page->has('lodge.visited_on', 1));

        $this->actingAs($this->secretary)->get(route('admin.meetings.visiting', ['clubSlug' => $this->club->slug]))
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Meetings/Visiting')
                ->where('visitors', 1)
                ->where('visits.0.member', 'Tom Hart')
                ->where('visits.0.lodge_slug', 'unity-lodge-5')
                ->missing('visits.0.note'));
    }

    public function test_a_visit_needs_a_lodge_and_a_date_that_has_passed(): void
    {
        $this->actingAs($this->tom)->post(route('members.visits.store'), ['visited_on' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors(['lodge_name', 'visited_on']);
        $this->actingAs($this->tom)->post(route('members.visits.store'), ['lodge_slug' => 'no-such-lodge', 'visited_on' => now()->toDateString()])->assertNotFound();

        $this->assertSame(0, MemberVisit::count());
    }

    public function test_only_the_member_can_remove_their_visit_and_other_lodges_do_not_see_it(): void
    {
        $visit = MemberVisit::create(['user_id' => $this->tom->id, 'lodge_name' => 'St John No 1234', 'visited_on' => now()->subDay()]);
        $otherLodge = Club::factory()->create();
        $otherSecretary = User::factory()->create();
        $otherLodge->users()->attach($otherSecretary->id, ['role' => 'coach', 'status' => 'active']);

        $this->actingAs($otherSecretary)->delete(route('members.visits.destroy', $visit->id))->assertNotFound();
        $this->actingAs($otherSecretary)->get(route('admin.meetings.visiting', ['clubSlug' => $otherLodge->slug]))->assertInertia(fn ($page) => $page->has('visits', 0));
        $this->actingAs($this->tom)->get(route('admin.meetings.visiting', ['clubSlug' => $this->club->slug]))->assertForbidden();

        $this->actingAs($this->tom)->delete(route('members.visits.destroy', $visit->id));
        $this->assertModelMissing($visit);
    }
}
