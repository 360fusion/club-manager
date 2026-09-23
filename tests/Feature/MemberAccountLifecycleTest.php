<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MemberAccountStatus;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Livewire\Members\MemberImportPage;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Services\MemberInvitationService;
use App\Mail\MemberInvitationMail;
use App\Models\Club;
use App\Models\ClubEmailTemplate;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class MemberAccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'lodge', 'available_modules' => ['members']]);
        $this->club = Club::create(['name' => 'Lodge of Fraternity', 'slug' => 'fraternity', 'club_type_id' => $type->id, 'is_active' => true]);
        $this->admin = User::factory()->create();
        $this->admin->clubs()->attach($this->club, ['role' => 'admin', 'status' => 'active']);
    }

    private function member(array $overrides = []): Member
    {
        static $n = 0;
        $n++;

        return Member::create($overrides + [
            'club_id' => $this->club->id,
            'first_name' => 'Walter'.$n,
            'last_name' => 'Ash',
            'email' => "walter{$n}@example.com",
            'masonic_rank' => 'Bro',
            'membership_status' => MembershipStatus::Active,
            'current_office' => LodgeOffice::Member,
        ]);
    }

    private function seedTemplates(): void
    {
        foreach (['account_invitation', 'account_invitation_reminder'] as $key) {
            DB::table('default_email_templates')->updateOrInsert(['template_key' => $key], [
                'name' => $key,
                'subject' => 'Hello {{member_name}}',
                'body_html' => '<p>{{inviter_name}} invites you</p><a href="{{invite_url}}">Go</a><p>{{expiry_days}} days</p>',
                'available_placeholders' => json_encode(['member_name', 'club_name', 'invite_url', 'expiry_days', 'inviter_name']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_accepting_the_invitation_ends_in_has_account(): void
    {
        Mail::fake();
        $member = $this->member();
        $sent = app(MemberInvitationService::class)->invite($member, $this->club, $this->admin);
        $token = last(explode('/', $sent['url']));

        $this->get(route('invitation.accept', ['slug' => $this->club->slug, 'token' => $token]))->assertOk();

        $this->post(route('invitation.submit', ['slug' => $this->club->slug, 'token' => $token]), [
            'password' => 'a-Strong-passphrase-42',
            'password_confirmation' => 'a-Strong-passphrase-42',
        ])->assertRedirect(route('member.dashboard', ['slug' => $this->club->slug]));

        $this->assertSame(MemberAccountStatus::HasAccount, $member->fresh()->accountStatus($this->club));
        $this->assertAuthenticatedAs($member->fresh()->user);
    }

    public function test_someone_with_a_verified_account_must_confirm_their_password_not_replace_it(): void
    {
        Mail::fake();
        $existing = User::factory()->create(['email' => 'real@example.com', 'password' => Hash::make('their-own-secret')]);
        $member = $this->member(['email' => 'real@example.com']);
        $sent = app(MemberInvitationService::class)->invite($member, $this->club, $this->admin);
        $token = last(explode('/', $sent['url']));

        $this->post(route('invitation.submit', ['slug' => $this->club->slug, 'token' => $token]), [
            'password' => 'an-attackers-new-password',
            'password_confirmation' => 'an-attackers-new-password',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('their-own-secret', $existing->fresh()->password));
        $this->assertGuest();
    }

    public function test_an_invitation_that_has_expired_cannot_be_accepted(): void
    {
        Mail::fake();
        $member = $this->member();
        $sent = app(MemberInvitationService::class)->invite($member, $this->club, $this->admin);
        $token = last(explode('/', $sent['url']));

        $this->travel(15)->days();

        $this->assertSame(MemberAccountStatus::InviteExpired, $member->fresh()->accountStatus($this->club));
        $this->get(route('invitation.accept', ['slug' => $this->club->slug, 'token' => $token]))->assertRedirect(route('login'));
    }

    public function test_reminders_go_out_once_after_the_clubs_delay_and_only_for_live_invitations(): void
    {
        Mail::fake();
        $this->seedTemplates();
        $service = app(MemberInvitationService::class);

        $recent = $this->member();
        $due = $this->member();
        $expired = $this->member();
        $service->invite($recent, $this->club, $this->admin);
        $service->invite($due, $this->club, $this->admin);
        $service->invite($expired, $this->club, $this->admin);

        $setInvitedAt = fn (Member $m, $when) => $this->club->users()->updateExistingPivot($m->fresh()->user_id, ['invited_at' => $when]);
        $setInvitedAt($recent, now()->subDays(2));
        $setInvitedAt($due, now()->subDays(8));
        $setInvitedAt($expired, now()->subDays(20));
        Mail::fake();

        $this->artisan('app:send-invitation-reminders')->assertSuccessful();

        Mail::assertQueued(MemberInvitationMail::class, 1);
        Mail::assertQueued(MemberInvitationMail::class, fn ($mail) => $mail->reminder && $mail->hasTo($due->fresh()->user->email));
        $this->assertNotNull($this->club->users()->where('users.id', $due->fresh()->user_id)->first()->pivot->invitation_reminded_at);

        $this->artisan('app:send-invitation-reminders')->assertSuccessful();
        Mail::assertQueued(MemberInvitationMail::class, 1);
    }

    public function test_reminders_can_be_switched_off_per_club(): void
    {
        Mail::fake();
        $this->club->update(['settings' => ['invite_reminder_days' => 0]]);
        $member = $this->member();
        app(MemberInvitationService::class)->invite($member, $this->club, $this->admin);
        $this->club->users()->updateExistingPivot($member->fresh()->user_id, ['invited_at' => now()->subDays(10)]);
        Mail::fake();

        $this->artisan('app:send-invitation-reminders')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_a_reminder_reuses_the_original_link(): void
    {
        Mail::fake();
        $member = $this->member();
        $service = app(MemberInvitationService::class);
        $sent = $service->invite($member, $this->club, $this->admin);
        $this->club->users()->updateExistingPivot($member->fresh()->user_id, ['invited_at' => now()->subDays(8)]);
        Mail::fake();

        $service->remind($this->club, $member->fresh()->user);

        Mail::assertQueued(MemberInvitationMail::class, fn ($mail) => $mail->acceptUrl === $sent['url'] && $mail->reminder);
    }

    public function test_registration_is_linked_to_a_matching_record_only_when_an_admin_approves_a_verified_user(): void
    {
        $member = $this->member(['email' => 'joiner@example.com']);
        $joiner = User::factory()->create(['email' => 'joiner@example.com']);
        $this->club->users()->attach($joiner, ['role' => 'member', 'status' => 'pending']);

        $this->assertNull($member->fresh()->user_id, 'Registering alone must not link anything.');
        $this->assertSame($member->id, app(MemberInvitationService::class)->matchForUser($this->club, $joiner)?->id);

        $this->actingAs($this->admin)->post(route('clubs.members.approve', ['slug' => $this->club->slug, 'userId' => $joiner->id]))->assertRedirect();

        $this->assertSame($joiner->id, $member->fresh()->user_id);
        $this->assertSame(MemberAccountStatus::HasAccount, $member->fresh()->accountStatus($this->club));
    }

    public function test_an_unverified_registrant_is_not_linked_on_approval_but_is_once_they_verify(): void
    {
        $member = $this->member(['email' => 'late@example.com']);
        $joiner = User::factory()->unverified()->create(['email' => 'late@example.com']);
        $this->club->users()->attach($joiner, ['role' => 'member', 'status' => 'pending']);

        $this->actingAs($this->admin)->post(route('clubs.members.approve', ['slug' => $this->club->slug, 'userId' => $joiner->id]));
        $this->assertNull($member->fresh()->user_id);

        $this->get(URL::signedRoute('verification.verify', ['id' => $joiner->id, 'hash' => sha1($joiner->getEmailForVerification())]));

        $this->assertNotNull($member->fresh()->user_id);
    }

    public function test_invite_only_clubs_refuse_join_requests_through_registration(): void
    {
        $this->club->update(['settings' => ['registration_mode' => 'invite_only']]);

        $this->post('/register', [
            'name' => 'Walk In',
            'email' => 'walkin@example.com',
            'password' => 'a-Strong-passphrase-42',
            'password_confirmation' => 'a-Strong-passphrase-42',
            'club' => $this->club->slug,
        ])->assertSessionHasErrors('email');

        $this->assertNull(User::where('email', 'walkin@example.com')->first());
    }

    public function test_open_clubs_still_take_join_requests(): void
    {
        $this->post('/register', [
            'name' => 'Walk In',
            'email' => 'walkin@example.com',
            'password' => 'a-Strong-passphrase-42',
            'password_confirmation' => 'a-Strong-passphrase-42',
            'club' => $this->club->slug,
        ])->assertRedirect(route('login'));

        $this->assertSame('pending', $this->club->users()->where('users.email', 'walkin@example.com')->first()->pivot->status);
    }

    public function test_csv_import_puts_people_on_the_roster_and_can_invite_them(): void
    {
        Mail::fake();
        Storage::fake('local');
        $csv = "name,email\nAda Lovelace,ada@example.com\nBad Row,not-an-email\n";

        Livewire::actingAs($this->admin)->test(MemberImportPage::class, ['clubSlug' => $this->club->slug])
            ->set('file', UploadedFile::fake()->createWithContent('m.csv', $csv))
            ->call('reviewRows')
            ->set('invite', true)
            ->call('runImport');

        $member = Member::where('club_id', $this->club->id)->where('email', 'ada@example.com')->firstOrFail();
        $this->assertSame('Ada', $member->first_name);
        $this->assertSame(MemberAccountStatus::Invited, $member->accountStatus($this->club));
        Mail::assertQueued(MemberInvitationMail::class, fn ($mail) => $mail->hasTo('ada@example.com'));
    }

    public function test_csv_import_without_the_option_leaves_them_not_invited(): void
    {
        Mail::fake();
        Storage::fake('local');

        Livewire::actingAs($this->admin)->test(MemberImportPage::class, ['clubSlug' => $this->club->slug])
            ->set('file', UploadedFile::fake()->createWithContent('m.csv', "name,email\nGrace Hopper,grace@example.com\n"))
            ->call('reviewRows')
            ->call('runImport');

        $member = Member::where('club_id', $this->club->id)->where('email', 'grace@example.com')->firstOrFail();
        $this->assertSame(MemberAccountStatus::NotInvited, $member->accountStatus($this->club));
        Mail::assertNothingQueued();
    }

    public function test_a_lodge_can_reword_the_invitation_but_must_keep_the_link(): void
    {
        $this->seedTemplates();

        $this->actingAs($this->admin)->put(route('admin.email_templates.update', ['clubSlug' => $this->club->slug, 'key' => 'account_invitation']), [
            'subject' => 'Welcome',
            'body_html' => '<p>No link here</p>',
        ])->assertSessionHasErrors('body_html');

        $this->actingAs($this->admin)->put(route('admin.email_templates.update', ['clubSlug' => $this->club->slug, 'key' => 'account_invitation']), [
            'subject' => 'Welcome {{member_name}}',
            'body_html' => '<p>Join us: {{invite_url}}</p>',
        ])->assertSessionDoesntHaveErrors();

        $this->assertTrue(ClubEmailTemplate::where('club_id', $this->club->id)->where('template_key', 'account_invitation')->exists());
    }

    public function test_the_users_page_invite_actions_share_the_same_rules(): void
    {
        Mail::fake();
        $this->seedTemplates();
        $member = $this->member(['membership_status' => MembershipStatus::Resigned, 'email' => 'gone@example.com']);
        $user = User::factory()->create(['email' => 'gone@example.com']);
        $this->club->users()->attach($user, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($this->admin)->post(route('admin.users.invite', ['clubSlug' => $this->club->slug, 'userId' => $user->id]))->assertSessionHas('error');

        Mail::assertNothingQueued();
    }

    public function test_the_users_page_lists_join_requests_with_their_matching_record(): void
    {
        $this->member(['first_name' => 'Ivy', 'last_name' => 'Match', 'email' => 'ivy@example.com']);
        $joiner = User::factory()->create(['email' => 'ivy@example.com']);
        $this->club->users()->attach($joiner, ['role' => 'member', 'status' => 'pending']);

        $this->actingAs($this->admin)->get(route('admin.users.index', ['clubSlug' => $this->club->slug]))
            ->assertInertia(fn ($page) => $page->where('members', fn ($members) => collect($members)->contains(fn ($m) => $m['email'] === 'ivy@example.com' && $m['matching_member'] === 'Ivy Match')));
    }
}
