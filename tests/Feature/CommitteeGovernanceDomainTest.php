<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\AttendanceType;
use App\Domains\ClubAccounting\Enums\CommitteeItemType;
use App\Domains\ClubAccounting\Enums\CommitteeMeetingStatus;
use App\Domains\ClubAccounting\Enums\TaskStatus;
use App\Domains\ClubAccounting\Livewire\Committee\LiveMinuteTaker;
use App\Domains\ClubAccounting\Livewire\Committee\MeetingIndex;
use App\Domains\ClubAccounting\Livewire\Committee\MeetingWorkspace;
use App\Domains\ClubAccounting\Livewire\Committee\Modals\BillAuditModal;
use App\Domains\ClubAccounting\Livewire\Committee\Modals\CandidateVettingModal;
use App\Domains\ClubAccounting\Models\ClubCommitteeAgendaItem;
use App\Domains\ClubAccounting\Models\ClubCommitteeAttendee;
use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Domains\ClubAccounting\Models\ClubCommitteeTask;
use App\Domains\ClubAccounting\Models\ClubNoticeOfMotion;
use App\Domains\ClubAccounting\Notifications\CommitteeTaskAssignedNotification;
use App\Domains\ClubAccounting\Services\Governance\CommitteeNotesParserService;
use App\Domains\ClubAccounting\Services\Governance\NoticeOfMotionBridgeService;
use App\Domains\ClubAccounting\Services\Integration\MemberMentionSearchService;
use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class CommitteeGovernanceDomainTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;
    private User $admin;
    private User $member1;
    private User $member2;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'lodge',
            'available_modules' => ['accounting', 'meetings'],
        ]);

        $this->club = Club::create([
            'name' => 'The Lodge of Fraternity No. 1234',
            'slug' => 'lodge-of-fraternity',
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create(['name' => 'Arthur Pendelton', 'email' => 'arthur@lodge.org']);
        $this->admin->clubs()->attach($this->club, ['role' => 'admin']);

        $this->member1 = User::factory()->create(['name' => 'James Sterling', 'email' => 'james@lodge.org']);
        $this->member1->clubs()->attach($this->club, ['role' => 'member']);

        $this->member2 = User::factory()->create(['name' => 'Thomas Alwin', 'email' => 'thomas@lodge.org']);
        $this->member2->clubs()->attach($this->club, ['role' => 'member']);

        // Seed basic accounts
        Account::create(['club_id' => $this->club->id, 'code' => '1000', 'name' => 'Operating Bank Account', 'type' => 'asset', 'current_balance' => 4500.00]);
        Account::create(['club_id' => $this->club->id, 'code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset', 'current_balance' => 0]);
        Account::create(['club_id' => $this->club->id, 'code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'current_balance' => 0]);
    }

    public function test_admin_can_schedule_committee_meeting_via_livewire(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(MeetingIndex::class, ['clubSlug' => $this->club->slug])
            ->set('newTitle', 'Regular Lodge Committee - October 2026')
            ->set('newDate', Carbon::now()->addDays(5)->format('Y-m-d\TH:i'))
            ->set('newLocation', 'Committee Room 1, Masonic Hall')
            ->call('createMeeting')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('club_acc_committee_meetings', [
            'club_id' => $this->club->id,
            'title' => 'Regular Lodge Committee - October 2026',
            'status' => 'scheduled',
        ]);
    }

    public function test_committee_workspace_roll_call_and_agenda_management(): void
    {
        $this->actingAs($this->admin);

        $meeting = ClubCommitteeMeeting::create([
            'club_id' => $this->club->id,
            'title' => 'Executive Committee',
            'meeting_date' => Carbon::now()->addDays(2),
            'status' => CommitteeMeetingStatus::Scheduled,
        ]);

        Livewire::test(MeetingWorkspace::class, [
            'clubSlug' => $this->club->slug,
            'meetingId' => $meeting->id,
        ])
            ->set('selectedUserId', $this->member1->id)
            ->set('attendeeRole', 'Treasurer')
            ->call('addAttendee')
            ->assertHasNoErrors()
            ->set('agendaTitle', 'Review Dining Fee Increase')
            ->set('agendaItemType', 'motion')
            ->set('agendaDescription', 'Discussion on raising dinner fee to £28.')
            ->call('addAgendaItem')
            ->assertHasNoErrors()
            ->call('setStatus', 'in_progress');

        $this->assertDatabaseHas('club_acc_committee_attendees', [
            'committee_meeting_id' => $meeting->id,
            'user_id' => $this->member1->id,
            'role_title' => 'Treasurer',
            'attendance_type' => 'present',
        ]);

        $this->assertDatabaseHas('club_acc_committee_agenda_items', [
            'committee_meeting_id' => $meeting->id,
            'title' => 'Review Dining Fee Increase',
            'item_type' => 'motion',
        ]);

        $this->assertEquals(CommitteeMeetingStatus::InProgress, $meeting->fresh()->status);
    }

    public function test_notes_parser_service_extracts_mentions_tasks_and_motions(): void
    {
        $parser = new CommitteeNotesParserService();

        $notes = <<<TEXT
Opened committee meeting at 19:30.
Present: @Arthur Pendelton and @James Sterling.

[ ] @James Sterling Inspect dining hall heating before next meeting due: 2026-10-25
[ ] Order new lodge summons paper stock

/motion That annual lodge dues be increased to £160 commencing January 2027
TEXT;

        $result = $parser->parse($notes, $this->club->id);

        $this->assertCount(2, $result['mentions']);
        $this->assertEquals('Arthur Pendelton', $result['mentions'][0]['name']);
        $this->assertEquals('James Sterling', $result['mentions'][1]['name']);

        $this->assertCount(2, $result['tasks']);
        $this->assertStringContainsString('Inspect dining hall heating', $result['tasks'][0]['title']);
        $this->assertEquals('James Sterling', $result['tasks'][0]['assigned_to_name']);
        $this->assertEquals('2026-10-25', $result['tasks'][0]['due_date']);

        $this->assertCount(1, $result['motions']);
        $this->assertStringContainsString('annual lodge dues be increased to £160', $result['motions'][0]['motion_text']);
    }

    public function test_live_minute_taker_syncs_extracted_tasks_and_motions_into_database(): void
    {
        $this->actingAs($this->admin);

        $meeting = ClubCommitteeMeeting::create([
            'club_id' => $this->club->id,
            'title' => 'Pre-Meeting Committee',
            'meeting_date' => Carbon::now(),
            'status' => CommitteeMeetingStatus::InProgress,
        ]);

        $notes = <<<TEXT
[ ] @James Sterling Reconcile lodge bank accounts by 2026-11-01
/motion That Brother William Ward be recommended for honorary membership
TEXT;

        Livewire::test(LiveMinuteTaker::class, [
            'clubSlug' => $this->club->slug,
            'meetingId' => $meeting->id,
        ])
            ->set('notesRaw', $notes)
            ->call('syncEntities')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('club_acc_committee_tasks', [
            'committee_meeting_id' => $meeting->id,
            'assigned_to_user_id' => $this->member1->id,
            'due_date' => '2026-11-01',
        ]);

        $this->assertDatabaseHas('club_acc_notices_of_motion', [
            'club_id' => $this->club->id,
            'committee_meeting_id' => $meeting->id,
            'status' => 'draft_committee',
        ]);
    }

    public function test_notice_of_motion_bridge_service_exports_to_summons_builder(): void
    {
        $meeting = ClubCommitteeMeeting::create([
            'club_id' => $this->club->id,
            'title' => 'Committee Meeting',
            'meeting_date' => Carbon::now(),
            'status' => CommitteeMeetingStatus::InProgress,
        ]);

        $motion = ClubNoticeOfMotion::create([
            'club_id' => $this->club->id,
            'committee_meeting_id' => $meeting->id,
            'proposer_user_id' => $this->admin->id,
            'title' => 'Subscription Revision',
            'motion_text' => 'That the annual subscription of the lodge be £150 per annum.',
            'rationale' => 'To meet increased provincial dues and hall tenancy fees.',
            'status' => 'approved_for_summons',
        ]);

        $lodgeMeeting = Meeting::create([
            'club_id' => $this->club->id,
            'title' => 'Regular Meeting No. 540',
            'meeting_date' => Carbon::now()->addDays(14),
            'starts_at' => '17:30',
            'venue' => 'Masonic Hall, Oxford',
            'dress_code' => 'Dark Suit',
            'status' => 'draft',
            'custom_agenda_items' => [],
        ]);

        $bridge = new NoticeOfMotionBridgeService();
        $success = $bridge->exportToSummons($motion, $lodgeMeeting);

        $this->assertTrue($success);

        $lodgeMeeting->refresh();
        $motion->refresh();

        $this->assertEquals('published_on_summons', $motion->status);
        $this->assertNotNull($motion->exported_to_summons_at);

        $items = $lodgeMeeting->agendaItems;
        $this->assertNotEmpty($items);
        $this->assertStringContainsString('NOTICE OF MOTION: Subscription Revision', $items[0]->title);
        $this->assertStringContainsString('Arthur Pendelton', $items[0]->description);
    }

    public function test_member_mention_search_service(): void
    {
        $service = new MemberMentionSearchService();

        $results = $service->search($this->club->id, 'Sterling');
        $this->assertCount(1, $results);
        $this->assertEquals('James Sterling', $results[0]['name']);
        $this->assertEquals('@James Sterling', $results[0]['mention_tag']);
    }

    public function test_task_assigned_notification_dispatches_properly(): void
    {
        Notification::fake();

        $meeting = ClubCommitteeMeeting::create([
            'club_id' => $this->club->id,
            'title' => 'Committee Meeting',
            'meeting_date' => Carbon::now(),
            'status' => CommitteeMeetingStatus::InProgress,
        ]);

        $task = ClubCommitteeTask::create([
            'committee_meeting_id' => $meeting->id,
            'assigned_to_user_id' => $this->member1->id,
            'title' => 'Audit kitchen crockery inventary',
            'due_date' => Carbon::now()->addDays(7),
            'status' => TaskStatus::Pending,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(LiveMinuteTaker::class, [
            'clubSlug' => $this->club->slug,
            'meetingId' => $meeting->id,
        ])
            ->call('notifyAssignedMember', $task->id);

        Notification::assertSentTo($this->member1, CommitteeTaskAssignedNotification::class, function ($n) use ($task) {
            return $n->task->id === $task->id;
        });
    }

    public function test_candidate_vetting_and_bill_audit_modals(): void
    {
        $this->actingAs($this->admin);

        $meeting = ClubCommitteeMeeting::create([
            'club_id' => $this->club->id,
            'title' => 'Vetting & Audit Committee',
            'meeting_date' => Carbon::now(),
            'status' => CommitteeMeetingStatus::Scheduled,
        ]);

        // Candidate vetting modal test
        Livewire::test(CandidateVettingModal::class)
            ->call('loadCandidate', $this->member2->id, $meeting->id)
            ->set('vettingNotes', 'Candidate interviewed. Proposer credentials confirmed.')
            ->set('isRecommended', true)
            ->call('signOffCandidate')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('club_acc_committee_agenda_items', [
            'committee_meeting_id' => $meeting->id,
            'item_type' => 'candidate_vetting',
            'is_approved' => true,
        ]);

        // Bill audit modal test
        $bill = Bill::create([
            'club_id' => $this->club->id,
            'bill_number' => 'BILL-2026-0042',
            'vendor_name' => 'Masonic Hall Catering Co',
            'category' => 'Catering & Dining',
            'amount' => 840.00,
            'due_date' => Carbon::now()->addDays(10),
            'status' => 'unpaid',
        ]);

        Livewire::test(BillAuditModal::class)
            ->call('loadBill', $bill->id, $meeting->id)
            ->set('auditNotes', 'Catering covers checked against attendance sheet. Amounts match.')
            ->set('isApprovedForPayment', true)
            ->call('signOffBill')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('club_acc_committee_agenda_items', [
            'committee_meeting_id' => $meeting->id,
            'item_type' => 'accounts_audit',
            'is_approved' => true,
        ]);
    }

    public function test_admin_can_access_committee_routes_via_http(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.committee.index', $this->club->slug));
        $response->assertOk();
        $response->assertSee('Lodge Committee');

        $meeting = ClubCommitteeMeeting::create([
            'club_id' => $this->club->id,
            'title' => 'Regular Committee Meeting',
            'meeting_date' => Carbon::now()->addDays(7),
            'status' => CommitteeMeetingStatus::Scheduled,
        ]);

        $responseWorkspace = $this->get(route('admin.committee.workspace', [$this->club->slug, $meeting->id]));
        $responseWorkspace->assertOk();

        $responseMinutes = $this->get(route('admin.committee.minutes', [$this->club->slug, $meeting->id]));
        $responseMinutes->assertOk();
    }
}
