<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Lodge;
use App\Models\LodgeClaim;
use App\Models\LodgeClaimEvent;
use App\Models\LodgeSchedule;
use App\Models\MasonicHall;
use App\Models\Province;
use App\Models\RecurringRule;
use App\Models\User;
use App\Notifications\LodgeClaimNotification;
use App\Services\Lodges\LodgeClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Tests\TestCase;

class LodgeClaimTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $secretary;

    private Lodge $lodge;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Craft Lodge', 'code' => 'craft_lodge', 'available_modules' => ['memberships'], 'default_settings' => []]);
        $province = Province::create(['name' => 'Province of Durham', 'code' => 'durham']);
        $hall = MasonicHall::factory()->create(['province_id' => $province->id, 'name' => 'Gateshead Masonic Hall', 'town' => 'Gateshead']);

        $this->superAdmin = User::factory()->create(['is_super_admin' => true, 'name' => 'Super Admin']);
        $this->secretary = User::factory()->create(['name' => 'Sam Secretary']);
        $this->lodge = Lodge::factory()->create([
            'club_type_id' => $type->id,
            'name' => 'Lodge of Industry',
            'number' => '48',
            'slug' => 'lodge-of-industry-48',
            'province_id' => $province->id,
            'masonic_hall_id' => $hall->id,
            'installation_month' => 10,
        ]);
        LodgeSchedule::factory()->create(['lodge_id' => $this->lodge->id, 'occurrence' => '4th', 'day_of_week' => 'Monday', 'months' => [1, 2, 3, 4, 5, 9, 10, 11], 'start_time' => '18:45']);
    }

    private function claimData(array $overrides = []): array
    {
        return [
            'claimant_role' => 'secretary',
            'message' => 'I am the Secretary and would like to manage our lodge here.',
            'confirm' => true,
            ...$overrides,
        ];
    }

    private function submit(?User $user = null, ?Lodge $lodge = null, array $overrides = []): LodgeClaim
    {
        return app(LodgeClaimService::class)->submit(
            $lodge ?? $this->lodge,
            $user ?? $this->secretary,
            collect($this->claimData($overrides))->only(['claimant_role', 'message', 'phone', 'evidence'])->all(),
        );
    }

    public function test_only_a_signed_in_person_can_claim(): void
    {
        $this->get(route('lodges.claim', $this->lodge->slug))->assertRedirect(route('login'));
        $this->post(route('lodges.claim.store', $this->lodge->slug), $this->claimData())->assertRedirect(route('login'));

        $this->assertSame(0, LodgeClaim::count());
    }

    public function test_the_claim_form_is_offered_for_a_lodge_that_nobody_manages(): void
    {
        $this->actingAs($this->secretary)->get(route('lodges.claim', $this->lodge->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Lodges/Claim')->where('lodge.name', 'Lodge of Industry')->has('roles', 5));
    }

    public function test_a_managed_or_unlisted_lodge_cannot_be_claimed(): void
    {
        $club = Club::create(['club_type_id' => $this->lodge->club_type_id, 'name' => 'Managed', 'slug' => 'managed', 'status' => 'active']);
        $this->lodge->forceFill(['club_id' => $club->id])->save();

        $this->actingAs($this->secretary)->get(route('lodges.claim', $this->lodge->slug))
            ->assertRedirect(route('lodges.show', $this->lodge->slug))->assertSessionHas('error');
        $this->actingAs($this->secretary)->post(route('lodges.claim.store', $this->lodge->slug), $this->claimData())->assertSessionHasErrors('lodge');

        $gone = Lodge::factory()->create(['slug' => 'gone', 'status' => 'erased']);
        $this->actingAs($this->secretary)->get(route('lodges.claim', $gone->slug))->assertNotFound();
        $this->assertSame(0, LodgeClaim::count());
    }

    public function test_submitting_a_claim_records_it_and_tells_every_superadmin_and_nobody_else(): void
    {
        Notification::fake();
        $second = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($this->secretary)->post(route('lodges.claim.store', $this->lodge->slug), $this->claimData(['evidence' => 'Installed as Secretary in 2024', 'phone' => '0191 000 0000']))
            ->assertRedirect(route('members.lodges'))->assertSessionHas('success');

        $claim = LodgeClaim::firstOrFail();
        $this->assertSame('pending', $claim->status);
        $this->assertSame($this->secretary->id, $claim->user_id);
        $this->assertSame('Installed as Secretary in 2024', $claim->evidence);
        $this->assertSame(['submitted'], $claim->events->pluck('type')->all());

        Notification::assertSentTo([$this->superAdmin, $second], LodgeClaimNotification::class, fn ($n) => str_contains($n->title, 'Lodge of Industry') && $n->route === 'superadmin.lodge_claims.index');
        Notification::assertNotSentTo($this->secretary, LodgeClaimNotification::class);
        Notification::assertCount(2);
    }

    public function test_a_claim_needs_a_real_role_a_reason_and_the_confirmation(): void
    {
        foreach ([['claimant_role' => 'emperor'], ['message' => 'short'], ['confirm' => false], ['claimant_role' => '']] as $bad) {
            $this->actingAs($this->secretary)->post(route('lodges.claim.store', $this->lodge->slug), $this->claimData($bad))->assertSessionHasErrors();
        }

        $this->assertSame(0, LodgeClaim::count());
    }

    public function test_one_open_claim_per_person_per_lodge_and_a_limit_overall(): void
    {
        Notification::fake();
        $this->submit();

        $this->actingAs($this->secretary)->post(route('lodges.claim.store', $this->lodge->slug), $this->claimData())->assertSessionHasErrors('lodge');
        $this->actingAs($this->secretary)->get(route('lodges.claim', $this->lodge->slug))->assertRedirect(route('members.lodges'));

        foreach (range(1, LodgeClaim::MAX_OPEN_PER_USER - 1) as $i) {
            $this->submit(lodge: Lodge::factory()->create(['slug' => "extra-{$i}"]));
        }

        $this->actingAs($this->secretary)->post(route('lodges.claim.store', Lodge::factory()->create(['slug' => 'one-too-many'])->slug), $this->claimData())->assertSessionHasErrors('lodge');
        $this->assertSame(LodgeClaim::MAX_OPEN_PER_USER, LodgeClaim::count());
    }

    public function test_approving_creates_a_club_the_claimant_owns_and_links_the_listing(): void
    {
        Notification::fake();
        $claim = $this->submit();
        $other = $this->submit(User::factory()->create());

        $this->actingAs($this->superAdmin)->post(route('superadmin.lodge_claims.approve', $claim->id), ['mode' => 'new', 'note' => 'Checked with the Provincial Secretary.'])
            ->assertSessionHas('success');

        $club = Club::where('slug', 'lodge-of-industry')->firstOrFail();
        $this->assertSame('Lodge of Industry', $club->name);
        $this->assertSame('48', $club->lodge_number);
        $this->assertSame($this->lodge->club_type_id, $club->club_type_id);
        $this->assertSame($this->lodge->province_id, $club->province_id);
        $this->assertSame($this->lodge->masonic_hall_id, $club->masonic_hall_id);
        $this->assertSame('October', $club->settings['installation_month']);
        $this->assertSame('owner', $club->users()->whereKey($this->secretary->id)->first()->pivot->role);
        $this->assertSame('active', $club->users()->whereKey($this->secretary->id)->first()->pivot->status);

        $rule = RecurringRule::where('club_id', $club->id)->firstOrFail();
        $this->assertSame('4th', $rule->occurrence);
        $this->assertSame('Monday', $rule->day_of_week);
        $this->assertSame([1, 2, 3, 4, 5, 9, 10, 11], $rule->active_months);
        $this->assertStringStartsWith('18:45', (string) $rule->default_start_time);
        $this->assertSame('Gateshead Masonic Hall', $rule->default_venue);

        $lodge = $this->lodge->refresh();
        $this->assertSame($club->id, $lodge->club_id);
        $this->assertNotNull($lodge->claimed_at);

        $claim->refresh();
        $this->assertSame('approved', $claim->status);
        $this->assertSame($this->superAdmin->id, $claim->decided_by);
        $this->assertSame($club->id, $claim->club_id);
        $this->assertSame(['submitted', 'approved'], $claim->events->pluck('type')->all());

        $this->assertSame('superseded', $other->refresh()->status);
        Notification::assertSentTo($this->secretary, LodgeClaimNotification::class, fn ($n) => str_starts_with($n->title, 'You now manage'));
        Notification::assertSentTo($other->user, LodgeClaimNotification::class, fn ($n) => str_contains($n->title, 'was not approved'));
    }

    public function test_the_new_owner_can_open_their_lodges_admin_area(): void
    {
        Notification::fake();
        $claim = $this->submit();
        app(LodgeClaimService::class)->approveWithNewClub($claim, $this->superAdmin);

        $this->actingAs($this->secretary)->get(route('admin.settings.show', ['clubSlug' => 'lodge-of-industry']))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('admin.settings.show', ['clubSlug' => 'lodge-of-industry']))->assertForbidden();
    }

    public function test_a_club_slug_that_is_taken_gets_the_lodge_number(): void
    {
        Notification::fake();
        Club::create(['club_type_id' => $this->lodge->club_type_id, 'name' => 'Other', 'slug' => 'lodge-of-industry', 'status' => 'active']);

        $club = app(LodgeClaimService::class)->approveWithNewClub($this->submit(), $this->superAdmin);

        $this->assertSame('lodge-of-industry-48', $club->slug);
    }

    public function test_approving_can_link_an_existing_club_without_handing_out_access(): void
    {
        Notification::fake();
        $existing = Club::create(['club_type_id' => $this->lodge->club_type_id, 'name' => 'Lodge of Industry (existing)', 'slug' => 'existing', 'status' => 'active']);
        $claim = $this->submit();

        $this->actingAs($this->superAdmin)->post(route('superadmin.lodge_claims.approve', $claim->id), ['mode' => 'link', 'club_id' => $existing->id])->assertSessionHasNoErrors();

        $this->assertSame($existing->id, $this->lodge->refresh()->club_id);
        $this->assertSame('linked', $claim->refresh()->events->last()->type);
        $this->assertFalse($existing->users()->whereKey($this->secretary->id)->exists());
        $this->assertSame(1, Club::count());
    }

    public function test_linking_can_make_the_claimant_an_admin_but_never_an_owner_and_a_club_links_once(): void
    {
        Notification::fake();
        $existing = Club::create(['club_type_id' => $this->lodge->club_type_id, 'name' => 'Existing', 'slug' => 'existing', 'status' => 'active']);

        $this->actingAs($this->superAdmin)->post(route('superadmin.lodge_claims.approve', $this->submit()->id), ['mode' => 'link', 'club_id' => $existing->id, 'make_claimant_admin' => true]);
        $this->assertSame('admin', $existing->users()->whereKey($this->secretary->id)->first()->pivot->role);

        $second = Lodge::factory()->create(['slug' => 'second']);
        $secondClaim = $this->submit(User::factory()->create(), $second);
        $this->actingAs($this->superAdmin)->post(route('superadmin.lodge_claims.approve', $secondClaim->id), ['mode' => 'link', 'club_id' => $existing->id])->assertSessionHasErrors('club_id');
        $this->assertNull($second->refresh()->club_id);
        $this->assertSame('pending', $secondClaim->refresh()->status);
    }

    public function test_a_claim_needs_a_reason_to_be_refused_and_the_person_is_told(): void
    {
        Notification::fake();
        $claim = $this->submit();

        $this->actingAs($this->superAdmin)->post(route('superadmin.lodge_claims.reject', $claim->id), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame('pending', $claim->refresh()->status);

        $this->actingAs($this->superAdmin)->post(route('superadmin.lodge_claims.reject', $claim->id), ['reason' => 'We could not confirm you are an officer.'])->assertSessionHas('success');

        $this->assertSame('rejected', $claim->refresh()->status);
        $this->assertNull($this->lodge->refresh()->club_id);
        Notification::assertSentTo($this->secretary, LodgeClaimNotification::class, fn ($n) => $n->body === 'We could not confirm you are an officer.');

        // A refused person may ask again.
        $this->assertSame('pending', $this->submit()->status);
    }

    public function test_the_team_can_ask_a_question_and_the_claimant_can_answer_it(): void
    {
        Notification::fake();
        $claim = $this->submit();

        $this->actingAs($this->superAdmin)->post(route('superadmin.lodge_claims.request_info', $claim->id), ['question' => 'Which year were you installed?'])->assertSessionHas('success');
        $this->assertSame('more_info', $claim->refresh()->status);
        Notification::assertSentTo($this->secretary, LodgeClaimNotification::class, fn ($n) => $n->body === 'Which year were you installed?');

        $this->actingAs($this->secretary)->get(route('members.lodges'))
            ->assertInertia(fn ($page) => $page->where('claims.0.status', 'more_info')->where('claims.0.question', 'Which year were you installed?'));

        $this->actingAs(User::factory()->create())->post(route('lodges.claim.respond', $claim->id), ['reply' => 'It was me'])->assertNotFound();

        $this->actingAs($this->secretary)->post(route('lodges.claim.respond', $claim->id), ['reply' => '2024, under the Master.'])->assertSessionHas('success');
        $this->assertSame('pending', $claim->refresh()->status);
        $this->assertSame(['submitted', 'more_info_requested', 'info_provided'], $claim->events->pluck('type')->all());
        Notification::assertSentTo($this->superAdmin, LodgeClaimNotification::class, fn ($n) => str_contains($n->body, 'replied'));

        // Nothing to answer any more.
        $this->actingAs($this->secretary)->post(route('lodges.claim.respond', $claim->id), ['reply' => 'Again'])->assertSessionHasErrors('claim');
    }

    public function test_a_claimant_can_withdraw_and_only_the_claimant(): void
    {
        Notification::fake();
        $claim = $this->submit();

        $this->actingAs(User::factory()->create())->delete(route('lodges.claim.withdraw', $claim->id))->assertNotFound();
        $this->assertSame('pending', $claim->refresh()->status);

        $this->actingAs($this->secretary)->delete(route('lodges.claim.withdraw', $claim->id))->assertSessionHas('success');
        $this->assertSame('withdrawn', $claim->refresh()->status);
        $this->actingAs($this->secretary)->delete(route('lodges.claim.withdraw', $claim->id))->assertSessionHasErrors('claim');
    }

    public function test_a_claim_that_is_settled_cannot_be_decided_again(): void
    {
        Notification::fake();
        $claim = $this->submit();
        app(LodgeClaimService::class)->approveWithNewClub($claim, $this->superAdmin);

        $this->actingAs($this->superAdmin)->post(route('superadmin.lodge_claims.reject', $claim->id), ['reason' => 'Changed my mind'])->assertSessionHasErrors('claim');
        $this->actingAs($this->superAdmin)->post(route('superadmin.lodge_claims.approve', $claim->id), ['mode' => 'new'])->assertSessionHasErrors('claim');

        $this->assertSame('approved', $claim->refresh()->status);
        $this->assertSame(1, Club::count());
    }

    public function test_only_superadmins_can_decide_claims_or_see_the_queue(): void
    {
        Notification::fake();
        $claim = $this->submit();

        $this->actingAs($this->secretary)->get(route('superadmin.lodge_claims.index'))->assertRedirect('/');
        $this->actingAs($this->secretary)->post(route('superadmin.lodge_claims.approve', $claim->id), ['mode' => 'new']);
        $this->actingAs($this->secretary)->post(route('superadmin.lodge_claims.reject', $claim->id), ['reason' => 'no']);

        $this->assertSame('pending', $claim->refresh()->status);
        $this->assertNull($this->lodge->refresh()->club_id);
    }

    public function test_the_queue_shows_claims_with_the_evidence_and_the_badge_count(): void
    {
        Notification::fake();
        $this->submit(overrides: ['evidence' => 'Ask the Provincial Secretary']);

        $this->actingAs($this->superAdmin)->get(route('superadmin.lodge_claims.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SuperAdmin/LodgeClaims/Index')
                ->has('claims.data', 1)
                ->where('claims.data.0.evidence', 'Ask the Provincial Secretary')
                ->where('claims.data.0.claimant.email', $this->secretary->email)
                ->where('claims.data.0.lodge.hall', fn ($hall) => str_contains($hall, 'Gateshead'))
                ->where('counts.open', 1)
                ->where('pendingLodgeClaims', 1));

        $this->actingAs($this->secretary)->get(route('members.lodges'))->assertInertia(fn ($page) => $page->where('pendingLodgeClaims', 0));
    }

    public function test_the_lodge_page_says_where_my_claim_stands(): void
    {
        Notification::fake();
        $this->actingAs($this->secretary)->get(route('lodges.show', $this->lodge->slug))
            ->assertInertia(fn ($page) => $page->where('lodge.can_claim', true)->where('lodge.claim', null));

        $this->submit();

        $this->actingAs($this->secretary)->get(route('lodges.show', $this->lodge->slug))
            ->assertInertia(fn ($page) => $page->where('lodge.claim.status', 'pending'));
        $this->actingAs(User::factory()->create())->get(route('lodges.show', $this->lodge->slug))
            ->assertInertia(fn ($page) => $page->where('lodge.claim', null));
    }

    public function test_a_superadmin_can_link_and_take_back_a_listing_directly(): void
    {
        Notification::fake();
        $club = Club::create(['club_type_id' => $this->lodge->club_type_id, 'name' => 'Existing', 'slug' => 'existing', 'status' => 'active']);
        $claim = $this->submit();

        $this->actingAs($this->superAdmin)->post(route('superadmin.lodges.link_club', $this->lodge->id), ['club_id' => $club->id])->assertSessionHasNoErrors();
        $this->assertSame($club->id, $this->lodge->refresh()->club_id);
        $this->assertSame('superseded', $claim->refresh()->status);

        $this->actingAs($this->superAdmin)->post(route('superadmin.lodges.link_club', Lodge::factory()->create(['slug' => 'other'])->id), ['club_id' => $club->id])->assertSessionHasErrors('club_id');

        $this->actingAs($this->superAdmin)->delete(route('superadmin.lodges.unlink_club', $this->lodge->id))->assertSessionHas('success');
        $this->assertNull($this->lodge->refresh()->club_id);
        $this->assertModelExists($club);
    }

    public function test_taking_back_a_claimed_listing_is_written_into_its_history(): void
    {
        Notification::fake();
        $claim = $this->submit();
        app(LodgeClaimService::class)->approveWithNewClub($claim, $this->superAdmin);

        $this->actingAs($this->superAdmin)->delete(route('superadmin.lodges.unlink_club', $this->lodge->id));

        $this->assertSame('unlinked', $claim->refresh()->events->last()->type);
        $this->assertNull($this->lodge->refresh()->club_id);
    }

    public function test_the_history_of_a_claim_cannot_be_rewritten(): void
    {
        Notification::fake();
        $event = $this->submit()->events()->firstOrFail();

        $this->expectException(LogicException::class);
        $event->update(['note' => 'Something else']);
    }

    public function test_the_history_of_a_claim_cannot_be_deleted(): void
    {
        Notification::fake();
        $event = $this->submit()->events()->firstOrFail();

        try {
            $event->delete();
            $this->fail('A claim event was deleted.');
        } catch (LogicException) {
            $this->assertSame(1, LodgeClaimEvent::count());
        }
    }
}
