<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\CollectionType;
use App\Domains\ClubAccounting\Livewire\Charity\CharityDashboard;
use App\Domains\ClubAccounting\Livewire\Committee\AgendaPackPreviewModal;
use App\Domains\ClubAccounting\Livewire\Committee\MeetingIndex;
use App\Domains\ClubAccounting\Livewire\Committee\MeetingWorkspace;
use App\Domains\ClubAccounting\Livewire\Members\MemberProfile;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Concerns\PlantsForeignRecords;
use Tests\TestCase;

/**
 * Record ids that travel in the request body (a meeting to update, an account to post to, a member to name)
 * are checked by validation for existence only. As an admin of a club with records of its own, send ids that
 * belong to two other clubs and check none of them is stored, updated or shown.
 */
class CrossClubBodyIdsTest extends TestCase
{
    use PlantsForeignRecords;
    use RefreshDatabase;

    private Club $attackerClub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $type = ClubType::first();
        $this->attackerClub = Club::withoutEvents(fn () => Club::create(['club_type_id' => $type->id, 'name' => 'Attacker Club', 'slug' => 'attacker-club', 'status' => 'active']));
        $attacker = User::factory()->create(['id' => 9000]);
        $this->attackerClub->users()->attach($attacker->id, ['role' => 'owner', 'status' => 'active']);

        $this->plantForeignRecords([$this->attackerClub->id, ...$this->foreignClubIds()]);

        $this->withExceptionHandling();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->actingAs($attacker);
    }

    /**
     * @return list<int>
     */
    private function foreignClubIds(): array
    {
        return Club::where('id', '!=', $this->attackerClub->id)->pluck('id')->all();
    }

    /**
     * @return list<int>
     */
    private function foreignIds(string $table): array
    {
        return DB::table($table)->whereIn('club_id', $this->foreignClubIds())->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Submit a form the way a browser would: starting from the fields under test, add whatever else validation
     * asks for until the request gets past it.
     *
     * @param  array<string, mixed>  $payload
     */
    private function submit(string $method, string $route, array $payload, array $routeParams = []): TestResponse
    {
        $url = route($route, ['clubSlug' => $this->attackerClub->slug, ...$routeParams]);
        $response = $this->send($method, $url, $payload);

        for ($round = 0; $round < 8; $round++) {
            $errors = $this->validationErrors($response);

            if ($errors === []) {
                break;
            }

            $payload = $this->fillFromErrors($payload, array_diff_key($errors, array_flip(array_keys($payload))));
            $response = $this->send($method, $url, $payload);
        }

        return $response;
    }

    /**
     * The attacker's own members: the one planted for it and a second, since a witness must differ from the counter.
     *
     * @return array{0: int, 1: int}
     */
    private function ownMembers(): array
    {
        $first = (int) DB::table('club_acc_members')->where('club_id', $this->attackerClub->id)->value('id');
        $columns = Schema::getColumns('club_acc_members');
        $allowed = [...$this->enumDefaults('club_acc_members'), ...$this->allowedValues('club_acc_members')];
        $second = (int) DB::table('club_acc_members')->insertGetId($this->rowFor('club_acc_members', $columns, $allowed, 'own2', ['club_id' => $this->attackerClub->id]));

        return [$first, $second];
    }

    public function test_meeting_save_cannot_take_over_another_clubs_meeting(): void
    {
        $foreign = DB::table('meetings')->whereIn('club_id', $this->foreignClubIds())->first();
        $this->assertNotNull($foreign, 'No foreign meeting was planted.');

        $this->submit('POST', 'admin.meetings.store', [
            'id' => $foreign->id,
            'title' => 'Hijacked by another club',
            'meeting_date' => '2027-01-15',
            'starts_at' => '18:00',
            'venue' => 'Elsewhere',
            'dress_code' => 'Dark suit',
            'dining_cost_member' => 10,
            'dining_cost_guest' => 10,
            'status' => 'draft',
        ]);

        $after = DB::table('meetings')->where('id', $foreign->id)->first();

        $this->assertSame((int) $foreign->club_id, (int) $after->club_id, 'The meeting moved into another club.');
        $this->assertSame($foreign->title, $after->title, "Another club's meeting was overwritten.");
    }

    public function test_journal_entry_cannot_post_to_another_clubs_ledger_account(): void
    {
        [$one, $two] = $this->foreignIds('accounting_accounts');

        $this->submit('POST', 'admin.accounting.journal.store', [
            'description' => 'Posting to someone else\'s ledger',
            'entry_date' => '2026-06-01',
            'items' => [['account_id' => $one, 'debit' => 10, 'credit' => 0], ['account_id' => $two, 'debit' => 0, 'credit' => 10]],
        ]);

        $this->assertSame(0, DB::table('accounting_journal_items')->whereIn('account_id', [$one, $two])->whereIn('journal_entry_id', DB::table('accounting_journal_entries')->where('club_id', $this->attackerClub->id)->pluck('id'))->count(), "A journal entry posted to another club's account.");
    }

    public function test_budget_cannot_be_set_on_another_clubs_ledger_account(): void
    {
        [$one] = $this->foreignIds('accounting_accounts');

        $this->submit('POST', 'admin.accounting.budget.update', [
            'financial_year' => 2026,
            'lines' => [['account_id' => $one, 'budgeted_amount' => 5]],
        ]);

        $this->assertSame(0, DB::table('accounting_budgets')->where('club_id', $this->attackerClub->id)->where('account_id', $one)->count(), "A budget line was set on another club's account.");
    }

    public function test_invoice_cannot_be_raised_for_someone_who_is_not_a_member(): void
    {
        $stranger = (int) DB::table('users')->whereNotIn('id', DB::table('club_user')->where('club_id', $this->attackerClub->id)->pluck('user_id'))->value('id');

        $this->submit('POST', 'admin.accounting.invoices.store', ['user_id' => $stranger, 'title' => 'Please pay', 'amount' => 25]);

        $this->assertSame(0, DB::table('invoices')->where('club_id', $this->attackerClub->id)->where('user_id', $stranger)->count(), 'An invoice was raised for a user outside the club.');
    }

    public function test_invoice_cannot_be_reassigned_to_someone_who_is_not_a_member(): void
    {
        $stranger = (int) DB::table('users')->whereNotIn('id', DB::table('club_user')->where('club_id', $this->attackerClub->id)->pluck('user_id'))->value('id');
        $own = DB::table('invoices')->where('club_id', $this->attackerClub->id)->first();
        $this->assertNotNull($own, 'No own invoice was planted.');

        $this->submit('PUT', 'admin.accounting.invoices.update', ['user_id' => $stranger, 'title' => 'Please pay', 'amount' => 25, 'status' => 'unpaid'], ['id' => $own->id]);

        $this->assertNotSame($stranger, (int) DB::table('invoices')->where('id', $own->id)->value('user_id'), 'An invoice was reassigned to a user outside the club.');
    }

    public function test_charity_collection_cannot_name_another_clubs_members(): void
    {
        [$foreignMember] = $this->foreignIds('club_acc_members');

        $this->submit('POST', 'admin.charity.collections.store', [
            'collection_type' => CollectionType::cases()[0]->value,
            'cash_amount' => 10,
            'cheque_amount' => 0,
            'notes' => 'Counted after the meeting',
            'counted_by_member_id' => $foreignMember,
            'witnessed_by_member_id' => $foreignMember,
            'donor_member_id' => $foreignMember,
        ]);

        $this->assertSame(0, DB::table('club_acc_charity_collections')->where('club_id', $this->attackerClub->id)->where(fn ($q) => $q->where('counted_by_member_id', $foreignMember)->orWhere('witnessed_by_member_id', $foreignMember)->orWhere('donor_member_id', $foreignMember))->whereRaw('cash_amount = 10')->count(), "A collection names another club's member.");
    }

    public function test_charity_grant_cannot_name_another_clubs_members_or_meeting(): void
    {
        [$foreignMember] = $this->foreignIds('club_acc_members');
        [$foreignMeeting] = $this->foreignIds('meetings');

        $this->submit('POST', 'admin.charity.grants.store', [
            'recipient_name' => 'Someone',
            'purpose' => 'Relief',
            'amount' => 50,
            'proposer_member_id' => $foreignMember,
            'seconder_member_id' => $foreignMember,
            'meeting_id' => $foreignMeeting,
        ]);

        $this->assertSame(0, DB::table('club_acc_charity_grants')->where('club_id', $this->attackerClub->id)->where('recipient_name', 'Someone')->where(fn ($q) => $q->where('proposer_member_id', $foreignMember)->orWhere('seconder_member_id', $foreignMember)->orWhere('meeting_id', $foreignMeeting))->count(), "A grant names another club's member or meeting.");
    }

    public function test_charity_dashboard_forms_cannot_name_another_clubs_members(): void
    {
        [$foreignMember] = $this->foreignIds('club_acc_members');
        [$foreignMeeting] = $this->foreignIds('club_acc_committee_meetings');
        [$ownMember, $ownOther] = $this->ownMembers();

        $component = Livewire::test(CharityDashboard::class, ['clubSlug' => $this->attackerClub->slug]);

        $component->set('cash_amount', '10.00')->set('counted_by_member_id', $foreignMember)->set('witnessed_by_member_id', $ownMember)->call('recordCollection');
        $component->set('recipient_name', 'Someone')->set('purpose', 'Relief')->set('grant_amount', '50.00')->set('proposer_member_id', $foreignMember)->set('committee_meeting_id', $foreignMeeting)->call('saveGrant');

        $this->assertSame(0, DB::table('club_acc_charity_collections')->where('club_id', $this->attackerClub->id)->where('counted_by_member_id', $foreignMember)->count(), "A collection names another club's member.");
        $this->assertSame(0, DB::table('club_acc_charity_grants')->where('club_id', $this->attackerClub->id)->where('recipient_name', 'Someone')->count(), "A grant was saved naming another club's member or meeting.");

        // The same forms do work with the club's own members, so the refusals above are down to the club check.
        $collectionsBefore = DB::table('club_acc_charity_collections')->where('club_id', $this->attackerClub->id)->count();
        $component->set('counted_by_member_id', $ownMember)->set('witnessed_by_member_id', $ownOther)->call('recordCollection');
        $component->set('proposer_member_id', $ownMember)->set('committee_meeting_id', (int) DB::table('club_acc_committee_meetings')->where('club_id', $this->attackerClub->id)->value('id'))->call('saveGrant');

        $this->assertSame($collectionsBefore + 1, DB::table('club_acc_charity_collections')->where('club_id', $this->attackerClub->id)->count(), 'The collection form no longer works for the own club.');
        $this->assertSame(1, DB::table('club_acc_charity_grants')->where('club_id', $this->attackerClub->id)->where('recipient_name', 'Someone')->count(), 'The grant form no longer works for the own club.');
    }

    public function test_meeting_workspace_grant_cannot_name_another_clubs_members(): void
    {
        [$foreignMember] = $this->foreignIds('club_acc_members');
        $ownMeeting = (int) DB::table('club_acc_committee_meetings')->where('club_id', $this->attackerClub->id)->value('id');

        Livewire::test(MeetingWorkspace::class, ['clubSlug' => $this->attackerClub->slug, 'meetingId' => $ownMeeting])
            ->set('grantRecipient', 'Someone')->set('grantPurpose', 'Relief')->set('grantAmount', '50.00')->set('grantProposerId', $foreignMember)
            ->call('saveCharityGrantFromMeeting');

        $this->assertSame(0, DB::table('club_acc_charity_grants')->where('club_id', $this->attackerClub->id)->where('recipient_name', 'Someone')->where('proposer_member_id', $foreignMember)->count(), "A grant names another club's member.");

        [$ownMember] = $this->ownMembers();

        Livewire::test(MeetingWorkspace::class, ['clubSlug' => $this->attackerClub->slug, 'meetingId' => $ownMeeting])
            ->set('grantRecipient', 'Someone')->set('grantPurpose', 'Relief')->set('grantAmount', '50.00')->set('grantProposerId', $ownMember)
            ->call('saveCharityGrantFromMeeting');

        $this->assertSame(1, DB::table('club_acc_charity_grants')->where('club_id', $this->attackerClub->id)->where('recipient_name', 'Someone')->where('proposer_member_id', $ownMember)->count(), 'The meeting grant form no longer works for the own club.');
    }

    public function test_agenda_pack_cannot_mark_another_clubs_committee_members_as_sent(): void
    {
        [$foreignAttendee] = $this->foreignIds('club_acc_committee_attendees') ?: [null];
        $ownMeeting = (int) DB::table('club_acc_committee_meetings')->where('club_id', $this->attackerClub->id)->value('id');

        if ($foreignAttendee === null) {
            $foreignAttendee = (int) DB::table('club_acc_committee_attendees')->whereNotIn('committee_meeting_id', DB::table('club_acc_committee_meetings')->where('club_id', $this->attackerClub->id)->pluck('id'))->value('id');
        }

        $this->assertNotSame(0, $foreignAttendee, 'No foreign committee attendee was planted.');
        DB::table('club_acc_committee_attendees')->where('id', $foreignAttendee)->update(['pack_sent_at' => null]);

        Livewire::test(AgendaPackPreviewModal::class, ['clubSlug' => $this->attackerClub->slug, 'meetingId' => $ownMeeting])
            ->set('emailSubject', 'Agenda pack')->set('emailBody', 'Please find the pack')->set('selectedRecipientIds', [$foreignAttendee])
            ->call('sendAgendaPack');

        $this->assertNull(DB::table('club_acc_committee_attendees')->where('id', $foreignAttendee)->value('pack_sent_at'), "Another club's committee member was marked as sent the pack.");

        // The action does reach its update for the club's own attendees, so the refusal above is down to the club check.
        $ownAttendee = (int) DB::table('club_acc_committee_attendees')->where('committee_meeting_id', $ownMeeting)->value('id');
        $this->assertNotSame(0, $ownAttendee, 'No own committee attendee was planted.');
        DB::table('club_acc_committee_attendees')->where('id', $ownAttendee)->update(['pack_sent_at' => null]);

        Livewire::test(AgendaPackPreviewModal::class, ['clubSlug' => $this->attackerClub->slug, 'meetingId' => $ownMeeting])
            ->set('emailSubject', 'Agenda pack')->set('emailBody', 'Please find the pack')->set('selectedRecipientIds', [$ownAttendee])
            ->call('sendAgendaPack');

        $this->assertNotNull(DB::table('club_acc_committee_attendees')->where('id', $ownAttendee)->value('pack_sent_at'), 'sendAgendaPack no longer reaches its update for the own club.');
    }

    public function test_member_profile_cannot_link_to_or_overwrite_another_clubs_ledger_contact(): void
    {
        [$foreignContact] = $this->foreignIds('accounting_contacts');
        $ownContact = (int) DB::table('accounting_contacts')->where('club_id', $this->attackerClub->id)->value('id');
        $ownMember = (int) DB::table('club_acc_members')->where('club_id', $this->attackerClub->id)->value('id');
        $before = (array) DB::table('accounting_contacts')->where('id', $foreignContact)->first();

        $profile = Livewire::test(MemberProfile::class, ['clubSlug' => $this->attackerClub->slug, 'memberId' => $ownMember])
            ->set('first_name', 'Alex')->set('last_name', 'Attacker')->set('masonic_rank', 'Bro')->set('grand_rank', '')->set('provincial_rank', '')
            ->set('membership_status', 'active')->set('current_office', '')->set('middle_names', '')->set('preferred_name', '')
            ->set('email', 'attacker@example.test')->set('phone', '07000 000000')->set('address_line_1', '1 Road')->set('address_line_2', '')
            ->set('city', 'Attackerton')->set('county', 'Shire')->set('postcode', 'AB1 2CD')->set('country', 'UK')
            ->set('date_of_initiation', '')->set('date_of_passing', '')->set('date_of_raising', '')->set('date_of_joining', '');

        DB::table('club_acc_members')->where('id', $ownMember)->update(['customer_account_id' => null]);

        $profile->set('customer_account_id', $foreignContact)->call('updateProfile');
        $this->assertSame([], array_values(array_diff(array_keys($profile->errors()->toArray()), ['customer_account_id'])), 'The rest of the form should be valid, so the only thing refused is the contact.');

        $this->assertSame($before, (array) DB::table('accounting_contacts')->where('id', $foreignContact)->first(), "Another club's ledger contact was overwritten.");
        $this->assertNotSame($foreignContact, (int) DB::table('club_acc_members')->where('id', $ownMember)->value('customer_account_id'), "A member was linked to another club's ledger contact.");

        // The same form does work with the club's own contact, so the refusal above is down to the club check.
        $profile->set('customer_account_id', $ownContact)->call('updateProfile');
        $profile->assertHasNoErrors();

        $this->assertSame($ownContact, (int) DB::table('club_acc_members')->where('id', $ownMember)->value('customer_account_id'), 'The profile form no longer links the own club\'s contact.');
    }

    public function test_committee_meeting_cannot_link_to_another_clubs_regular_meeting(): void
    {
        [$foreignMeeting] = $this->foreignIds('meetings');
        $ownMeeting = (int) DB::table('meetings')->where('club_id', $this->attackerClub->id)->value('id');
        $count = fn () => DB::table('club_acc_committee_meetings')->where('club_id', $this->attackerClub->id)->count();
        $before = $count();

        $component = Livewire::test(MeetingIndex::class, ['clubSlug' => $this->attackerClub->slug]);

        $component->set('linked_regular_meeting_id', $foreignMeeting)->set('newTitle', 'Linked committee meeting')->set('newDate', '2026-09-01')->set('newLocation', 'Hall')->call('createMeeting');
        $this->assertSame($before, $count(), "A committee meeting was created linked to another club's meeting.");

        // The same form does work with the club's own meeting, so the refusal above is down to the club check.
        $component->set('linked_regular_meeting_id', $ownMeeting)->set('newTitle', 'Linked committee meeting')->set('newDate', '2026-09-01')->set('newLocation', 'Hall')->call('createMeeting');
        $this->assertSame($before + 1, $count(), 'The committee meeting form no longer works for the own club.');
        $this->assertSame($ownMeeting, (int) DB::table('club_acc_committee_meetings')->where('club_id', $this->attackerClub->id)->orderByDesc('id')->value('linked_regular_meeting_id'));
    }
}
