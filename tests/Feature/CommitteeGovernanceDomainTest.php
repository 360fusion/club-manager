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
use App\Domains\ClubAccounting\Livewire\Committee\AgendaPackPreviewModal;
use App\Domains\ClubAccounting\Mail\CommitteeAgendaPackMailable;
use App\Domains\ClubAccounting\Services\Governance\CommitteePackCompilerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

    public function test_can_assign_member_committee_roles_and_batch_add_to_roll_call(): void
    {
        $this->actingAs($this->admin);

        // 1. Assign committee roles to members
        $response1 = $this->post(route('admin.users.committee_role.update', [$this->club->slug, $this->member1->id]), [
            'committee_role' => 'chair',
        ]);
        $response1->assertRedirect();
        $this->assertDatabaseHas('club_user', [
            'club_id' => $this->club->id,
            'user_id' => $this->member1->id,
            'committee_role' => 'chair',
        ]);

        $response2 = $this->post(route('admin.users.committee_role.update', [$this->club->slug, $this->member2->id]), [
            'committee_role' => 'secretary',
        ]);
        $response2->assertRedirect();
        $this->assertDatabaseHas('club_user', [
            'club_id' => $this->club->id,
            'user_id' => $this->member2->id,
            'committee_role' => 'secretary',
        ]);

        // Non-committee member
        $guestMember = User::factory()->create(['name' => 'Robert Visitor', 'email' => 'robert@visitor.com']);
        $guestMember->clubs()->attach($this->club, ['role' => 'member', 'committee_role' => null]);

        // 2. Create meeting and test Livewire batch roll-call
        $meeting = ClubCommitteeMeeting::create([
            'club_id' => $this->club->id,
            'title' => 'Executive Committee',
            'meeting_date' => Carbon::now()->addDays(3),
            'status' => CommitteeMeetingStatus::Scheduled,
        ]);

        $lw = Livewire::test(MeetingWorkspace::class, [
            'clubSlug' => $this->club->slug,
            'meetingId' => $meeting->id,
        ]);

        // Verify selectAllCommittee() selects chair and secretary
        $lw->call('selectAllCommittee')
            ->assertSet('selectedMemberIds', [$this->member1->id, $this->member2->id]);

        // Add the non-committee member with a custom role
        $lw->set('selectedMemberIds', [$this->member1->id, $this->member2->id, $guestMember->id])
            ->set('customRoles.' . $guestMember->id, 'Lodge Steward / Guest')
            ->call('addSelectedAttendees')
            ->assertHasNoErrors();

        // Verify database attendees
        $this->assertDatabaseHas('club_acc_committee_attendees', [
            'committee_meeting_id' => $meeting->id,
            'user_id' => $this->member1->id,
            'role_title' => 'Committee Chair',
            'attendance_type' => 'present',
        ]);

        $this->assertDatabaseHas('club_acc_committee_attendees', [
            'committee_meeting_id' => $meeting->id,
            'user_id' => $this->member2->id,
            'role_title' => 'Committee Secretary',
            'attendance_type' => 'present',
        ]);

        $this->assertDatabaseHas('club_acc_committee_attendees', [
            'committee_meeting_id' => $meeting->id,
            'user_id' => $guestMember->id,
            'role_title' => 'Lodge Steward / Guest',
            'attendance_type' => 'present',
        ]);
    }

    public function test_live_minute_taker_integrates_agenda_items_and_outline(): void
    {
        $this->actingAs($this->admin);

        $meeting = ClubCommitteeMeeting::create([
            'club_id' => $this->club->id,
            'title' => 'Regular Meeting with Agenda',
            'meeting_date' => Carbon::now()->addDays(5),
            'status' => CommitteeMeetingStatus::Scheduled,
        ]);

        $item1 = ClubCommitteeAgendaItem::create([
            'committee_meeting_id' => $meeting->id,
            'order' => 1,
            'item_type' => CommitteeItemType::AccountsAudit,
            'title' => 'Audit Hall Invoices',
            'description' => 'Review quarterly electricity and maintenance invoices.',
            'recommendation_text' => 'Pass for payment',
        ]);

        $item2 = ClubCommitteeAgendaItem::create([
            'committee_meeting_id' => $meeting->id,
            'order' => 2,
            'item_type' => CommitteeItemType::Motion,
            'title' => 'Capitation Fee Review',
            'description' => 'Proposed £5 increase per member.',
        ]);

        Livewire::test(LiveMinuteTaker::class, [
            'clubSlug' => $this->club->slug,
            'meetingId' => $meeting->id,
        ])
            ->assertSee('Audit Hall Invoices')
            ->assertSee('Capitation Fee Review')
            ->call('loadAgendaOutline')
            ->assertSet('notesRaw', fn ($val) => str_contains($val, '### 1. Audit Hall Invoices'))
            ->assertSet('notesRaw', fn ($val) => str_contains($val, '### 2. Capitation Fee Review'))
            ->call('toggleAgendaApproval', $item1->id)
            ->assertHasNoErrors();

        $this->assertTrue($item1->fresh()->is_approved);
    }

    public function test_commit_detected_items_extracts_and_persists_tasks_and_motions(): void
    {
        $this->actingAs($this->admin);

        $meeting = ClubCommitteeMeeting::create([
            'club_id' => $this->club->id,
            'title' => 'Live Minutes Sync Test',
            'meeting_date' => Carbon::now()->addDays(2),
            'status' => CommitteeMeetingStatus::InProgress,
        ]);

        $editorText = "Meeting commenced at 19:30.\n\n"
            . "[ ] @MemberName Review dining hall contract by 2026-10-15\n\n"
            . "/motion That annual dues be increased to £120 per member\n";

        Livewire::test(LiveMinuteTaker::class, [
            'clubSlug' => $this->club->slug,
            'meetingId' => $meeting->id,
        ])
            ->call('commitDetectedItems', $editorText)
            ->assertDispatched('notify')
            ->assertSet('activeRightTab', 'tasks')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('club_acc_committee_tasks', [
            'committee_meeting_id' => $meeting->id,
            'title' => '@MemberName Review dining hall contract by 2026-10-15',
            'due_date' => '2026-10-15',
        ]);

        $this->assertDatabaseHas('club_acc_notices_of_motion', [
            'committee_meeting_id' => $meeting->id,
            'motion_text' => 'That annual dues be increased to £120 per member',
        ]);
    }

    public function test_sync_extracted_entities_method_and_toast_format(): void
    {
        $this->actingAs($this->admin);

        $meeting = ClubCommitteeMeeting::create([
            'club_id' => $this->club->id,
            'title' => 'Live Minutes Sync Alias Test',
            'meeting_date' => Carbon::now()->addDays(3),
            'status' => CommitteeMeetingStatus::InProgress,
        ]);

        $editorText = "Intro notes.\n\n"
            . "[ ] @BrotherSmith Setup projector and sound equipment by 2026-11-01\n\n"
            . "/motion Approve annual financial statements for audit submission\n";

        Livewire::test(LiveMinuteTaker::class, [
            'clubSlug' => $this->club->slug,
            'meetingId' => $meeting->id,
        ])
            ->call('syncExtractedEntities', $editorText)
            ->assertDispatched('notify', function ($eventName, ...$args) {
                $payload = is_array($args[0] ?? null) ? $args[0] : $args;
                $data = isset($payload['type']) ? $payload : ($payload[0] ?? []);
                return ($data['type'] ?? '') === 'success'
                    && str_contains($data['message'] ?? '', 'Successfully committed 1 tasks and 1 motions.');
            })
            ->assertSet('activeRightTab', 'tasks')
            ->assertHasNoErrors();

        $this->assertEquals($editorText, $meeting->fresh()->draft_notes);
        $this->assertEquals($editorText, $meeting->fresh()->notes_raw);

        $this->assertDatabaseHas('club_acc_committee_tasks', [
            'committee_meeting_id' => $meeting->id,
            'title' => '@BrotherSmith Setup projector and sound equipment by 2026-11-01',
            'due_date' => '2026-11-01',
        ]);

        $this->assertDatabaseHas('club_acc_notices_of_motion', [
            'committee_meeting_id' => $meeting->id,
            'motion_text' => 'Approve annual financial statements for audit submission',
        ]);
    }

    public function test_compiler_service_generates_pdf_and_email_body(): void
    {
        $meeting = ClubCommitteeMeeting::create([
            'club_id' => $this->club->id,
            'title' => 'Quarterly Finance & Governance Meeting',
            'meeting_date' => Carbon::now()->addDays(7),
            'location' => 'Lodge Temple & Committee Room',
            'status' => CommitteeMeetingStatus::Scheduled,
        ]);

        ClubCommitteeAttendee::create([
            'committee_meeting_id' => $meeting->id,
            'user_id' => $this->member1->id,
            'name' => $this->member1->name,
            'role_title' => 'Worshipful Master',
            'attendance_type' => AttendanceType::Present,
        ]);

        ClubCommitteeAgendaItem::create([
            'committee_meeting_id' => $meeting->id,
            'order' => 1,
            'item_type' => CommitteeItemType::General,
            'title' => 'Opening of Committee & Reading of Minutes',
            'description' => 'Confirm previous meeting minutes as approved.',
        ]);

        $compiler = app(CommitteePackCompilerService::class);

        // 1. Email body test
        $emailBody = $compiler->compileEmailBody($meeting);
        $this->assertStringContainsString('Quarterly Finance & Governance Meeting', $emailBody);
        $this->assertStringContainsString('Opening of Committee & Reading of Minutes', $emailBody);
        $this->assertStringContainsString('The Lodge of Fraternity No. 1234', $emailBody);

        // 2. PDF compilation test
        $pdfOutput = $compiler->compilePdf($meeting);
        $this->assertNotEmpty($pdfOutput);
        $this->assertStringStartsWith('%PDF', $pdfOutput);
    }

    public function test_compiler_service_dispatches_pack_and_updates_pack_sent_at(): void
    {
        Mail::fake();

        $meeting = ClubCommitteeMeeting::create([
            'club_id' => $this->club->id,
            'title' => 'Executive Committee Dispatch Test',
            'meeting_date' => Carbon::now()->addDays(4),
            'status' => CommitteeMeetingStatus::Scheduled,
        ]);

        $attendee = ClubCommitteeAttendee::create([
            'committee_meeting_id' => $meeting->id,
            'user_id' => $this->member1->id,
            'name' => $this->member1->name,
            'role_title' => 'Secretary',
            'attendance_type' => AttendanceType::Present,
            'pack_sent_at' => null,
        ]);

        $compiler = app(CommitteePackCompilerService::class);

        $sentCount = $compiler->dispatchPack(
            meeting: $meeting,
            recipientMemberIds: [$this->member1->id],
            emailSubject: 'Official Agenda Pack',
            customEmailBody: '<p>Please find attached the official agenda pack.</p>',
            attachPdf: true,
        );

        $this->assertEquals(1, $sentCount);

        Mail::assertQueued(CommitteeAgendaPackMailable::class, function ($mail) {
            return $mail->hasTo($this->member1->email)
                && $mail->emailSubject === 'Official Agenda Pack'
                && $mail->attachPdf === true;
        });

        $this->assertNotNull($attendee->fresh()->pack_sent_at);
    }

    public function test_agenda_pack_preview_modal_flow(): void
    {
        Mail::fake();
        $this->actingAs($this->admin);

        $meeting = ClubCommitteeMeeting::create([
            'club_id' => $this->club->id,
            'title' => 'Lodge Pack Preview Modal Test',
            'meeting_date' => Carbon::now()->addDays(2),
            'status' => CommitteeMeetingStatus::Scheduled,
        ]);

        ClubCommitteeAttendee::create([
            'committee_meeting_id' => $meeting->id,
            'user_id' => $this->member2->id,
            'name' => $this->member2->name,
            'role_title' => 'Treasurer',
            'attendance_type' => AttendanceType::Present,
        ]);

        Livewire::test(AgendaPackPreviewModal::class, [
            'clubSlug' => $this->club->slug,
            'meetingId' => $meeting->id,
        ])
            ->assertSet('activeTab', 'pdf')
            ->assertSet('isOpen', false)
            ->call('openModal')
            ->assertSet('isOpen', true)
            ->assertSee($meeting->title)
            ->assertSet('includePdfAttachment', true)
            ->set('emailSubject', 'Custom Subject for Lodge Committee')
            ->set('emailBody', 'Dear Brethren, please review before Friday.')
            ->call('sendAgendaPack')
            ->assertDispatched('pack-dispatched')
            ->assertDispatched('notify')
            ->assertSet('isOpen', false)
            ->assertHasNoErrors();

        Mail::assertQueued(CommitteeAgendaPackMailable::class, function ($mail) {
            return $mail->emailSubject === 'Custom Subject for Lodge Committee';
        });
    }

    public function test_committee_pack_pdf_controller_route(): void
    {
        $this->actingAs($this->admin);

        $meeting = ClubCommitteeMeeting::create([
            'club_id' => $this->club->id,
            'title' => 'PDF Controller Test Meeting',
            'meeting_date' => Carbon::now()->addDays(1),
            'status' => CommitteeMeetingStatus::Scheduled,
        ]);

        // Test route committee.pack.pdf
        $response = $this->get(route('committee.pack.pdf', ['meetingId' => $meeting->id]));
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');

        // Test route admin.committee.pack.pdf with download
        $downloadResponse = $this->get(route('admin.committee.pack.pdf', [
            'clubSlug' => $this->club->slug,
            'meetingId' => $meeting->id,
            'download' => 1,
        ]));
        $downloadResponse->assertStatus(200);
        $downloadResponse->assertHeader('content-type', 'application/pdf');
    }

    public function test_compliance_explainer_modals_rendered_in_workspace(): void
    {
        $this->actingAs($this->admin);

        $meeting = ClubCommitteeMeeting::create([
            'club_id' => $this->club->id,
            'title' => 'Explainer Modals Workspace Test',
            'meeting_date' => Carbon::now()->addDays(3),
            'status' => CommitteeMeetingStatus::Scheduled,
        ]);

        Livewire::test(MeetingWorkspace::class, [
            'clubSlug' => $this->club->slug,
            'meetingId' => $meeting->id,
        ])
            ->assertSee('Candidate Vetting')
            ->assertSee('Rule 159')
            ->assertSee('complianceModal = \'vetting\'', false)
            ->assertSee('Accounts Audit')
            ->assertSee('Rule 158')
            ->assertSee('complianceModal = \'audit\'', false)
            ->assertSee('Statutory Summons Gate')
            ->assertSee('Financial Governance Gate')
            ->assertSee('Constitutional Requirement')
            ->assertSee('Platform Workflow')
            ->assertSee('Downstream Impact')
            ->assertSee('no candidate may be balloted for initiation or joining in open lodge without prior recommendation')
            ->assertSee('the Lodge Committee is required to audit and examine all liabilities, bills, and demands')
            ->assertHasNoErrors();
    }
}
