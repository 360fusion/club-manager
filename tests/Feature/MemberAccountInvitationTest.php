<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MemberAccountStatus;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Services\MemberInvitationException;
use App\Domains\ClubAccounting\Services\MemberInvitationService;
use App\Mail\MemberInvitationMail;
use App\Models\Club;
use App\Models\ClubEmailTemplate;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MemberAccountInvitationTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $admin;

    private MemberInvitationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'lodge', 'available_modules' => ['members']]);
        $this->club = Club::create(['name' => 'Lodge of Fraternity', 'slug' => 'fraternity', 'club_type_id' => $type->id, 'is_active' => true]);
        $this->admin = User::factory()->create();
        $this->admin->clubs()->attach($this->club, ['role' => 'admin', 'status' => 'active']);
        $this->service = app(MemberInvitationService::class);
    }

    private function member(array $overrides = [], ?Club $club = null): Member
    {
        static $n = 0;
        $n++;

        return Member::create($overrides + [
            'club_id' => ($club ?? $this->club)->id,
            'first_name' => 'Henry'.$n,
            'last_name' => 'Vane',
            'email' => "henry{$n}@example.com",
            'masonic_rank' => 'Bro',
            'membership_status' => MembershipStatus::Active,
            'current_office' => LodgeOffice::Member,
        ]);
    }

    private function pivot(Member $member): ?object
    {
        return $this->club->users()->where('users.id', $member->fresh()->user_id)->first()?->pivot;
    }

    public function test_inviting_creates_a_pending_account_links_the_member_and_queues_the_email(): void
    {
        Mail::fake();
        $member = $this->member(['email' => 'Jane@Example.com']);

        $this->assertSame(MemberAccountStatus::NotInvited, $member->accountStatus($this->club));

        $sent = $this->service->invite($member, $this->club, $this->admin);

        $this->assertTrue($sent['emailed']);
        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame($user->id, $member->fresh()->user_id);

        $pivot = $this->pivot($member);
        $this->assertSame('pending', $pivot->status);
        $this->assertSame('member', $pivot->role);
        $this->assertNotNull($pivot->invitation_token);
        $this->assertSame($this->admin->id, (int) $pivot->invited_by);
        $this->assertSame(MemberAccountStatus::Invited, $member->fresh()->accountStatus($this->club));

        Mail::assertQueued(MemberInvitationMail::class, fn ($mail) => $mail->hasTo('jane@example.com') && $mail->acceptUrl === $sent['url']);
    }

    public function test_an_existing_user_is_reused_and_never_overwritten(): void
    {
        Mail::fake();
        $existing = User::factory()->create(['name' => 'Real Name', 'email' => 'same@example.com']);
        $password = $existing->password;
        $member = $this->member(['email' => 'same@example.com']);

        $this->service->invite($member, $this->club, $this->admin);

        $this->assertSame(1, User::where('email', 'same@example.com')->count());
        $this->assertSame('Real Name', $existing->fresh()->name);
        $this->assertSame($password, $existing->fresh()->password);
        $this->assertSame($existing->id, $member->fresh()->user_id);
    }

    public function test_someone_already_active_at_the_club_keeps_their_access_and_role(): void
    {
        Mail::fake();
        $treasurer = User::factory()->unverified()->create(['email' => 'tre@example.com']);
        $this->club->users()->attach($treasurer, ['role' => 'treasurer', 'status' => 'active']);
        $member = $this->member(['email' => 'tre@example.com']);

        $this->service->invite($member, $this->club, $this->admin);

        $pivot = $this->pivot($member);
        $this->assertSame('active', $pivot->status);
        $this->assertSame('treasurer', $pivot->role);
        $this->assertNotNull($pivot->invitation_token);
    }

    public function test_refusals_come_with_a_reason(): void
    {
        Mail::fake();

        $cases = [
            'No email address on file' => $this->member(['email' => null]),
            'Only active or honorary members can be invited' => $this->member(['membership_status' => MembershipStatus::Resigned]),
        ];

        foreach ($cases as $reason => $member) {
            try {
                $this->service->invite($member, $this->club, $this->admin);
                $this->fail("Expected refusal: {$reason}");
            } catch (MemberInvitationException $e) {
                $this->assertSame($reason, $e->getMessage());
            }
        }

        $invited = $this->member();
        $this->service->invite($invited, $this->club, $this->admin);

        $this->expectException(MemberInvitationException::class);
        $this->expectExceptionMessage('Already invited');
        $this->service->invite($invited->fresh(), $this->club, $this->admin);
    }

    public function test_a_member_with_an_account_cannot_be_invited_again(): void
    {
        $user = User::factory()->create();
        $this->club->users()->attach($user, ['role' => 'member', 'status' => 'active', 'invitation_accepted_at' => now()]);
        $member = $this->member(['user_id' => $user->id, 'email' => $user->email]);

        $this->assertSame(MemberAccountStatus::HasAccount, $member->accountStatus($this->club));

        $this->expectExceptionMessage('Already has an account');
        $this->service->invite($member, $this->club, $this->admin);
    }

    public function test_two_members_cannot_share_one_account(): void
    {
        Mail::fake();
        $first = $this->member(['email' => 'family@example.com']);
        $second = $this->member(['email' => 'family@example.com']);

        $this->service->invite($first, $this->club, $this->admin);

        $this->expectExceptionMessage('already belongs to');
        $this->service->invite($second, $this->club, $this->admin);
    }

    public function test_a_random_password_account_is_not_shown_as_having_an_account(): void
    {
        $user = User::factory()->unverified()->create();
        $this->club->users()->attach($user, ['role' => 'member', 'status' => 'active']);
        $member = $this->member(['user_id' => $user->id]);

        $this->assertSame(MemberAccountStatus::NotInvited, $member->accountStatus($this->club));
    }

    public function test_status_is_derived_for_every_state(): void
    {
        $expiry = 14;
        $now = now();

        $this->assertSame(MemberAccountStatus::NotInvited, MemberAccountStatus::resolve(null, false, null, null, false, $expiry));
        $this->assertSame(MemberAccountStatus::Invited, MemberAccountStatus::resolve('pending', true, $now->copy()->subDays(3), null, false, $expiry));
        $this->assertSame(MemberAccountStatus::InviteExpired, MemberAccountStatus::resolve('pending', true, $now->copy()->subDays(15), null, false, $expiry));
        $this->assertSame(MemberAccountStatus::AwaitingApproval, MemberAccountStatus::resolve('pending', false, null, null, true, $expiry));
        $this->assertSame(MemberAccountStatus::HasAccount, MemberAccountStatus::resolve('active', false, null, $now, false, $expiry));
        $this->assertSame(MemberAccountStatus::HasAccount, MemberAccountStatus::resolve('active', false, null, null, true, $expiry));
        $this->assertSame(MemberAccountStatus::Deactivated, MemberAccountStatus::resolve('past', false, null, null, true, $expiry));
    }

    public function test_the_list_scope_and_filters_agree_with_the_status(): void
    {
        Mail::fake();
        $none = $this->member();
        $invited = $this->member();
        $this->service->invite($invited, $this->club, $this->admin);

        $user = User::factory()->create();
        $this->club->users()->attach($user, ['role' => 'member', 'status' => 'active', 'invitation_accepted_at' => now()]);
        $has = $this->member(['user_id' => $user->id]);

        $rows = Member::where('club_id', $this->club->id)->withAccountState($this->club->id)->get()->keyBy('id');

        $this->assertSame(MemberAccountStatus::NotInvited, $rows[$none->id]->accountStatus($this->club));
        $this->assertSame(MemberAccountStatus::Invited, $rows[$invited->id]->accountStatus($this->club));
        $this->assertSame(MemberAccountStatus::HasAccount, $rows[$has->id]->accountStatus($this->club));

        $ids = fn (string $state) => Member::where('club_id', $this->club->id)->whereAccountState($state, $this->club->id)->pluck('id')->all();

        $this->assertSame([$none->id], $ids('not_invited'));
        $this->assertSame([$invited->id], $ids('invited'));
        $this->assertSame([$has->id], $ids('has_account'));
    }

    public function test_the_list_uses_a_fixed_number_of_queries(): void
    {
        Mail::fake();
        foreach (range(1, 6) as $i) {
            $this->service->invite($this->member(), $this->club, $this->admin);
        }

        DB::enableQueryLog();
        Member::where('club_id', $this->club->id)->withAccountState($this->club->id)->get()->each->accountStatus($this->club);

        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_resend_is_rate_limited_then_issues_a_new_link(): void
    {
        Mail::fake();
        $member = $this->member();
        $this->service->invite($member, $this->club, $this->admin);
        $first = $this->pivot($member)->invitation_token;

        try {
            $this->service->resend($member->fresh(), $this->club, $this->admin);
            $this->fail('A resend straight after sending should wait.');
        } catch (MemberInvitationException $e) {
            $this->assertStringContainsString('wait', $e->getMessage());
        }

        $this->travel(11)->minutes();
        $this->service->resend($member->fresh(), $this->club, $this->admin);

        $this->assertNotSame($first, $this->pivot($member)->invitation_token);
        Mail::assertQueued(MemberInvitationMail::class, 2);
    }

    public function test_revoking_removes_someone_who_never_had_an_account(): void
    {
        Mail::fake();
        $member = $this->member();
        $this->service->invite($member, $this->club, $this->admin);
        $user = User::findOrFail($member->fresh()->user_id);
        $user->forceFill(['email_verified_at' => null])->save();

        $this->service->revoke($this->club, $user);

        $this->assertNull($member->fresh()->user_id);
        $this->assertNull($this->club->users()->where('users.id', $user->id)->first());
        $this->assertSame(MemberAccountStatus::NotInvited, $member->fresh()->accountStatus($this->club));
    }

    public function test_changing_the_email_of_an_invited_member_revokes_the_invitation(): void
    {
        Mail::fake();
        $member = $this->member();
        $this->service->invite($member, $this->club, $this->admin);

        $member->fresh()->update(['email' => 'new.address@example.com']);

        $this->assertSame(MemberAccountStatus::NotInvited, $member->fresh()->accountStatus($this->club));
    }

    public function test_resigning_ends_ordinary_access_and_revokes_open_invites(): void
    {
        Mail::fake();
        $invited = $this->member();
        $this->service->invite($invited, $this->club, $this->admin);

        $user = User::factory()->create();
        $this->club->users()->attach($user, ['role' => 'member', 'status' => 'active', 'invitation_accepted_at' => now()]);
        $active = $this->member(['user_id' => $user->id]);

        $staff = User::factory()->create();
        $this->club->users()->attach($staff, ['role' => 'treasurer', 'status' => 'active', 'invitation_accepted_at' => now()]);
        $treasurer = $this->member(['user_id' => $staff->id]);

        foreach ([$invited, $active, $treasurer] as $member) {
            $member->fresh()->update(['membership_status' => MembershipStatus::Resigned]);
        }

        $this->assertSame(MemberAccountStatus::NotInvited, $invited->fresh()->accountStatus($this->club));
        $this->assertSame('past', $this->pivot($active)->status);
        $this->assertSame('active', $this->pivot($treasurer)->status);
    }

    public function test_bulk_invite_reports_results_and_ignores_other_clubs(): void
    {
        Mail::fake();
        $ok = $this->member();
        $noEmail = $this->member(['email' => null]);
        $already = $this->member();
        $this->service->invite($already, $this->club, $this->admin);

        $otherClub = Club::create(['name' => 'Other', 'slug' => 'other', 'club_type_id' => $this->club->club_type_id, 'is_active' => true]);
        $foreign = $this->member([], $otherClub);

        $result = $this->service->inviteMany($this->club, [$ok->id, $noEmail->id, $already->id, $foreign->id, 999999], $this->admin);

        $this->assertSame(1, $result['invited']);
        $this->assertSame(['No email address on file' => 1, 'Already invited' => 1], $result['skipped']);
        $this->assertNull($foreign->fresh()->user_id);
        $this->assertNull(User::where('email', $foreign->email)->first());
    }

    public function test_bulk_invite_is_capped(): void
    {
        $result = $this->service->inviteMany($this->club, range(1, MemberInvitationService::BULK_LIMIT + 5), $this->admin);

        $this->assertTrue($result['capped']);
    }

    public function test_registration_match_finds_only_one_unlinked_member_by_email(): void
    {
        $user = User::factory()->create(['email' => 'match@example.com']);
        $member = $this->member(['email' => 'MATCH@example.com']);

        $this->assertSame($member->id, $this->service->matchForUser($this->club, $user)?->id);

        $this->member(['email' => 'match@example.com']);
        $this->assertNull($this->service->matchForUser($this->club, $user), 'An ambiguous match must not link anyone.');
    }

    public function test_the_invitation_mail_shows_the_real_expiry_and_honours_a_lodge_override(): void
    {
        $this->seedInvitationTemplate();
        $this->club->update(['settings' => ['invite_expiration_days' => 30]]);
        $user = User::factory()->create(['name' => 'Jane']);

        $default = new MemberInvitationMail($this->club->fresh(), $user, 'tok', 'https://example.test/x', $this->admin);
        $default->assertSeeInHtml('30 days');
        $default->assertSeeInHtml('https://example.test/x');

        ClubEmailTemplate::create(['club_id' => $this->club->id, 'template_key' => 'account_invitation', 'subject' => 'Welcome to the lodge', 'body_html' => '<p>Custom for {{member_name}}: {{invite_url}}</p>']);

        $custom = new MemberInvitationMail($this->club->fresh(), $user, 'tok', 'https://example.test/x', $this->admin);
        $custom->assertSeeInHtml('Custom for Jane');
        $this->assertSame('Welcome to the lodge', $custom->envelope()->subject);
    }

    private function seedInvitationTemplate(): void
    {
        DB::table('default_email_templates')->updateOrInsert(['template_key' => 'account_invitation'], [
            'name' => 'Invite',
            'subject' => 'Invitation to join {{club_name}}',
            'body_html' => '<p>Hi {{member_name}}</p><a href="{{invite_url}}">Go</a><p>Expires in {{expiry_days}} days.</p>',
            'available_placeholders' => json_encode(['member_name', 'club_name', 'invite_url', 'expiry_days', 'inviter_name']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
