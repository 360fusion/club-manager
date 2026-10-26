<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Lodge;
use App\Models\LodgeCorrection;
use App\Models\LodgeSchedule;
use App\Models\MasonicHall;
use App\Models\Province;
use App\Models\RecurringRule;
use App\Models\User;
use App\Notifications\LodgeClaimNotification;
use App\Services\Lodges\LodgeCorrectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LodgeCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $member;

    private Lodge $lodge;

    private MasonicHall $newHall;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Craft Lodge', 'code' => 'craft_lodge', 'available_modules' => ['memberships'], 'default_settings' => []]);
        $province = Province::create(['name' => 'Province of Durham', 'code' => 'durham']);
        $hall = MasonicHall::factory()->create(['province_id' => $province->id, 'name' => 'Gateshead Masonic Hall', 'town' => 'Gateshead']);
        $this->newHall = MasonicHall::factory()->create(['province_id' => $province->id, 'name' => 'Sunderland Masonic Hall', 'town' => 'Sunderland']);

        $this->superAdmin = User::factory()->create(['is_super_admin' => true]);
        $this->member = User::factory()->create(['name' => 'Sam Suggester']);
        $this->lodge = Lodge::factory()->create([
            'club_type_id' => $type->id,
            'name' => 'Lodge of Industry',
            'number' => '48',
            'slug' => 'lodge-of-industry-48',
            'province_id' => $province->id,
            'masonic_hall_id' => $hall->id,
            'meets_text' => 'Last Tuesday in February, last Wednesday in May',
            'website_url' => 'https://old.example.org',
        ]);
        LodgeSchedule::factory()->create(['lodge_id' => $this->lodge->id, 'occurrence' => 'last', 'day_of_week' => 'Tuesday', 'months' => [2]]);
        LodgeSchedule::factory()->create(['lodge_id' => $this->lodge->id, 'occurrence' => 'last', 'day_of_week' => 'Wednesday', 'months' => [5]]);
    }

    private function suggest(array $changes, ?string $note = null, ?User $user = null, ?Lodge $lodge = null): LodgeCorrection
    {
        return app(LodgeCorrectionService::class)->submit($lodge ?? $this->lodge, $user ?? $this->member, $changes, $note);
    }

    private function apply(LodgeCorrection $correction, array $fields, array $values = [], ?string $note = null)
    {
        return $this->actingAs($this->superAdmin)->post(route('superadmin.lodge_corrections.apply', $correction->id), ['fields' => $fields, 'values' => $values, 'note' => $note]);
    }

    public function test_only_a_signed_in_person_can_suggest_a_fix(): void
    {
        $this->get(route('lodges.correction', $this->lodge->slug))->assertRedirect(route('login'));
        $this->post(route('lodges.correction.store', $this->lodge->slug), ['website_url' => 'https://new.example.org'])->assertRedirect(route('login'));

        $this->assertSame(0, LodgeCorrection::count());
    }

    public function test_the_form_shows_the_listing_and_the_halls_of_its_province(): void
    {
        MasonicHall::factory()->create(['name' => 'Elsewhere Hall']);

        $this->actingAs($this->member)->get(route('lodges.correction', $this->lodge->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Lodges/SuggestFix')
                ->where('lodge.current.website_url', 'https://old.example.org')
                ->has('halls', 2));
    }

    public function test_only_the_details_that_differ_from_the_listing_are_kept(): void
    {
        $this->actingAs($this->member)->post(route('lodges.correction.store', $this->lodge->slug), [
            'name' => 'Lodge of Industry',
            'number' => '48',
            'website_url' => 'https://new.example.org',
            'masonic_hall_id' => $this->newHall->id,
            'note' => 'Their new site went up in May.',
        ])->assertRedirect(route('lodges.show', $this->lodge->slug))->assertSessionHas('success');

        $correction = LodgeCorrection::firstOrFail();
        $this->assertEquals(['website_url' => 'https://new.example.org', 'masonic_hall_id' => $this->newHall->id], $correction->changes);
        $this->assertSame('pending', $correction->status);
        $this->assertSame($this->member->id, $correction->user_id);
    }

    public function test_emptying_the_website_or_number_asks_for_it_to_be_removed_but_an_empty_name_is_ignored(): void
    {
        $this->actingAs($this->member)->post(route('lodges.correction.store', $this->lodge->slug), ['website_url' => '', 'name' => '', 'note' => 'The site is gone.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['website_url' => null], LodgeCorrection::firstOrFail()->changes);
    }

    public function test_a_suggestion_must_change_something_or_say_something(): void
    {
        $this->actingAs($this->member)->post(route('lodges.correction.store', $this->lodge->slug), ['number' => '48'])
            ->assertSessionHasErrors(['changes' => 'Change at least one detail, or tell us what is wrong in the note.']);

        $this->assertSame(0, LodgeCorrection::count());
    }

    public function test_contact_details_are_refused_in_free_text(): void
    {
        foreach (['note' => 'Ask the secretary on secretary@example.org', 'meets_text' => 'Ring 0191 496 0000 for dates'] as $field => $text) {
            $this->actingAs($this->member)->post(route('lodges.correction.store', $this->lodge->slug), [$field => $text])
                ->assertSessionHasErrors([$field => "Please don't include anyone's contact details."]);
        }

        $this->assertSame(0, LodgeCorrection::count());
    }

    public function test_one_waiting_suggestion_per_person_per_lodge_and_a_limit_overall(): void
    {
        $this->suggest(['number' => '49']);

        $this->actingAs($this->member)->post(route('lodges.correction.store', $this->lodge->slug), ['number' => '50'])->assertSessionHasErrors('lodge');
        $this->actingAs($this->member)->get(route('lodges.correction', $this->lodge->slug))->assertRedirect(route('lodges.show', $this->lodge->slug));

        foreach (range(1, LodgeCorrection::MAX_OPEN_PER_USER - 1) as $i) {
            $this->suggest(['number' => '1'], lodge: Lodge::factory()->create(['slug' => "extra-{$i}", 'number' => '2']));
        }

        $another = Lodge::factory()->create(['slug' => 'one-too-many', 'number' => '2']);
        $this->actingAs($this->member)->post(route('lodges.correction.store', $another->slug), ['number' => '3'])->assertSessionHasErrors('lodge');
        $this->assertSame(LodgeCorrection::MAX_OPEN_PER_USER, LodgeCorrection::count());
    }

    public function test_applying_updates_the_listing_keeps_the_old_values_and_thanks_the_suggester(): void
    {
        Notification::fake();
        $correction = $this->suggest(['name' => 'Industry', 'number' => '4800', 'website_url' => null, 'masonic_hall_id' => $this->newHall->id, 'status' => 'dormant']);

        $this->apply($correction, ['name', 'number', 'website_url', 'masonic_hall_id', 'status'], [
            'name' => 'Industry', 'number' => '4800', 'masonic_hall_id' => $this->newHall->id, 'status' => 'dormant',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $lodge = $this->lodge->fresh();
        $this->assertSame('Industry', $lodge->name);
        $this->assertSame('4800', $lodge->number);
        $this->assertNull($lodge->website_url);
        $this->assertSame($this->newHall->id, $lodge->masonic_hall_id);
        $this->assertSame('dormant', $lodge->status);
        $this->assertSame('lodge-of-industry-48', $lodge->slug);

        $correction->refresh();
        $this->assertSame('applied', $correction->status);
        $this->assertSame($this->superAdmin->id, $correction->decided_by);
        $this->assertSame(['from' => 'https://old.example.org', 'to' => null], $correction->applied['website_url']);
        $this->assertSame(['from' => '48', 'to' => '4800'], $correction->applied['number']);

        Notification::assertSentTo($this->member, LodgeClaimNotification::class, fn ($n) => str_contains($n->title, 'is live') && $n->via($this->member) === ['database', 'mail']);
        Notification::assertCount(1);
    }

    public function test_applying_new_meeting_wording_replaces_every_pattern_and_sets_the_installation_month(): void
    {
        Notification::fake();
        $correction = $this->suggest(['meets_text' => '2nd Tuesday Jan to May, Sep to Nov at 6.30pm. Installation December']);

        $this->apply($correction, ['meets_text'], ['meets_text' => '2nd Tuesday Jan to May, Sep to Nov at 6.30pm. Installation December'])->assertSessionHasNoErrors();

        $lodge = $this->lodge->fresh(['schedules']);
        $this->assertSame(12, (int) $lodge->installation_month);
        $this->assertCount(1, $lodge->schedules);
        $schedule = $lodge->schedules->first();
        $this->assertSame(['2nd', 'Tuesday', [1, 2, 3, 4, 5, 9, 10, 11, 12], '18:30', 'correction'], [$schedule->occurrence, $schedule->day_of_week, $schedule->months, $schedule->start_time, $schedule->source]);
        $this->assertCount(2, $correction->fresh()->applied['schedules']['from']);
    }

    public function test_wording_the_parser_cannot_read_is_refused_and_nothing_changes(): void
    {
        $correction = $this->suggest(['meets_text' => 'Whenever the moon is full', 'number' => '4800']);

        $this->apply($correction, ['meets_text', 'number'], ['meets_text' => 'Whenever the moon is full', 'number' => '4800'])
            ->assertSessionHasErrors(['meets_text' => 'We could not read a meeting pattern from that wording. Edit it, or untick it.']);

        $lodge = $this->lodge->fresh(['schedules']);
        $this->assertSame('48', $lodge->number);
        $this->assertSame('Last Tuesday in February, last Wednesday in May', $lodge->meets_text);
        $this->assertCount(2, $lodge->schedules);
        $this->assertSame('pending', $correction->fresh()->status);
    }

    public function test_only_the_ticked_fields_are_applied_with_the_values_the_superadmin_left(): void
    {
        Notification::fake();
        $correction = $this->suggest(['name' => 'Lodge of Industri', 'number' => '4800']);

        $this->apply($correction, ['name'], ['name' => 'Lodge of Industry and Peace', 'number' => '4800'])->assertSessionHasNoErrors();

        $lodge = $this->lodge->fresh();
        $this->assertSame('Lodge of Industry and Peace', $lodge->name);
        $this->assertSame('48', $lodge->number);
        $this->assertSame(['name'], array_keys($correction->fresh()->applied));
    }

    public function test_a_name_cannot_be_applied_empty(): void
    {
        $correction = $this->suggest(['name' => 'Industry']);

        $this->apply($correction, ['name'], ['name' => ''])->assertSessionHasErrors(['name' => 'Name cannot be left empty.']);

        $this->assertSame('Lodge of Industry', $this->lodge->fresh()->name);
    }

    public function test_applying_to_a_claimed_lodge_tells_its_active_admins_in_the_bell_only_and_leaves_the_club_meetings_alone(): void
    {
        Notification::fake();
        $club = Club::create(['club_type_id' => $this->lodge->club_type_id, 'name' => 'Industry', 'slug' => 'industry', 'status' => 'active']);
        $this->lodge->forceFill(['club_id' => $club->id])->save();
        $rule = RecurringRule::create(['club_id' => $club->id, 'name' => 'Regular meeting', 'occurrence' => 'last', 'day_of_week' => 'Tuesday', 'active_months' => [2], 'default_start_time' => '18:30:00']);
        [$owner, $admin, $pastAdmin, $plainMember] = User::factory()->count(4)->create();
        $club->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
        $club->users()->attach($admin->id, ['role' => 'admin', 'status' => 'active']);
        $club->users()->attach($pastAdmin->id, ['role' => 'admin', 'status' => 'past']);
        $club->users()->attach($plainMember->id, ['role' => 'member', 'status' => 'active']);
        $correction = $this->suggest(['meets_text' => '2nd Tuesday Jan to May at 7pm']);

        $this->apply($correction, ['meets_text'], ['meets_text' => '2nd Tuesday Jan to May at 7pm'])->assertSessionHasNoErrors();

        Notification::assertSentTo([$owner, $admin], LodgeClaimNotification::class, fn ($n, $channels) => $channels === ['database'] && ! str_contains($n->body.$n->title, 'Sam Suggester'));
        Notification::assertNotSentTo([$pastAdmin, $plainMember], LodgeClaimNotification::class);
        $this->assertSame([2], $rule->fresh()->active_months);
    }

    public function test_turning_down_records_the_reason_and_tells_the_suggester(): void
    {
        Notification::fake();
        $correction = $this->suggest(['number' => '4800']);

        $this->actingAs($this->superAdmin)->post(route('superadmin.lodge_corrections.reject', $correction->id), ['reason' => 'UGLE still lists it as No. 48.'])
            ->assertSessionHasNoErrors();

        $correction->refresh();
        $this->assertSame('rejected', $correction->status);
        $this->assertSame('UGLE still lists it as No. 48.', $correction->decision_note);
        $this->assertSame('48', $this->lodge->fresh()->number);
        Notification::assertSentTo($this->member, LodgeClaimNotification::class, fn ($n) => $n->body === 'UGLE still lists it as No. 48.');
    }

    public function test_a_decided_suggestion_cannot_be_decided_again(): void
    {
        Notification::fake();
        $correction = $this->suggest(['number' => '4800']);
        $this->apply($correction, ['number'], ['number' => '4800'])->assertSessionHasNoErrors();

        $this->apply($correction, ['number'], ['number' => '9'])->assertSessionHasErrors(['correction' => 'This suggestion has already been dealt with.']);
        $this->actingAs($this->superAdmin)->post(route('superadmin.lodge_corrections.reject', $correction->id), ['reason' => 'Changed my mind'])
            ->assertSessionHasErrors('correction');

        $this->assertSame('4800', $this->lodge->fresh()->number);
        $this->assertSame('applied', $correction->fresh()->status);
    }

    public function test_only_a_superadmin_can_see_or_decide_suggestions(): void
    {
        $correction = $this->suggest(['number' => '4800']);

        $this->actingAs($this->member)->get(route('superadmin.lodge_corrections.index'))->assertRedirect(route('home'));
        $this->actingAs($this->member)->post(route('superadmin.lodge_corrections.apply', $correction->id), ['fields' => ['number'], 'values' => ['number' => '1']])->assertRedirect(route('home'));
        $this->actingAs($this->member)->post(route('superadmin.lodge_corrections.reject', $correction->id), ['reason' => 'No thanks'])->assertRedirect(route('home'));

        $this->assertSame('48', $this->lodge->fresh()->number);
        $this->actingAs($this->superAdmin)->get(route('superadmin.lodge_corrections.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('SuperAdmin/LodgeCorrections/Index')->has('corrections.data', 1)->where('counts.open', 1));
    }

    public function test_my_lodges_lists_only_my_own_suggestions(): void
    {
        $this->suggest(['number' => '4800']);
        $this->suggest(['number' => '4900'], user: User::factory()->create());

        $this->actingAs($this->member)->get(route('members.lodges'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('corrections', 1)->where('corrections.0.what', ['Number']));
    }
}
