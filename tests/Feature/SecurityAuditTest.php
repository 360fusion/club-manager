<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Livewire\Committee\MeetingWorkspace;
use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Domains\ClubAccounting\Models\Member;
use App\Mail\ContactFormSubmittedMail;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\Page;
use App\Models\User;
use App\Support\EmailVerification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Locked;
use Livewire\Livewire;
use ReflectionClass;
use Tests\TestCase;

/**
 * Each test asserts the SECURE behaviour, so a failure here is a live vulnerability.
 */
class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    private Club $a;

    private Club $b;

    private User $owner;

    private User $admin;

    private User $treasurer;

    private User $member;

    private User $pending;

    private User $adminB;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->a = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->b = Club::create(['club_type_id' => $type->id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']);
        $this->a->forceFill(['email' => 'contact@club-a.test'])->save();

        $this->owner = $this->join($this->a, 'owner');
        $this->admin = $this->join($this->a, 'admin');
        $this->treasurer = $this->join($this->a, 'treasurer');
        $this->member = $this->join($this->a, 'member', 'active', 'member-a@example.test');
        $this->pending = $this->join($this->a, 'member', 'pending');
        $this->adminB = $this->join($this->b, 'admin');
        $this->outsider = User::factory()->create();
    }

    private function join(Club $club, string $role, string $status = 'active', ?string $email = null): User
    {
        $user = User::factory()->create($email ? ['email' => $email] : []);
        $club->users()->attach($user->id, ['role' => $role, 'status' => $status]);

        return $user;
    }

    private function membershipStatus(Club $club, User $user): ?string
    {
        return $club->users()->where('users.id', $user->id)->first()?->pivot->status;
    }

    public function test_public_overview_does_not_expose_member_details(): void
    {
        $this->assertStringNotContainsString('member-a@example.test', (string) $this->get('/club-a/overview')->getContent());
    }

    public function test_only_club_staff_can_approve_or_reject_members(): void
    {
        $approve = fn (User $as) => $this->actingAs($as)->post(route('clubs.members.approve', ['slug' => 'club-a', 'userId' => $this->pending->id]));

        $approve($this->pending)->assertForbidden();
        $approve($this->outsider)->assertForbidden();
        $approve($this->adminB)->assertForbidden();
        $this->assertSame('pending', $this->membershipStatus($this->a, $this->pending));

        $this->actingAs($this->member)->post(route('clubs.members.reject', ['slug' => 'club-a', 'userId' => $this->owner->id]))->assertForbidden();
        $this->assertNotNull($this->membershipStatus($this->a, $this->owner));

        $approve($this->admin)->assertSessionHasNoErrors();
        $this->assertSame('active', $this->membershipStatus($this->a, $this->pending));
    }

    public function test_only_club_staff_can_change_the_custom_domain(): void
    {
        $this->actingAs($this->member)->post(route('clubs.domain.update', ['slug' => 'club-a']), ['custom_domain' => 'evil.example'])->assertForbidden();
        $this->actingAs($this->adminB)->post(route('clubs.domain.update', ['slug' => 'club-a']), ['custom_domain' => 'evil.example'])->assertForbidden();

        $this->assertNull($this->a->fresh()->custom_domain);
    }

    public function test_only_club_staff_can_import_or_export_members(): void
    {
        $csv = UploadedFile::fake()->createWithContent('m.csv', "Eve,evil@example.test,admin\n");

        $this->actingAs($this->member)->post(route('clubs.members.import', ['slug' => 'club-a']), ['csv_file' => $csv])->assertForbidden();
        $this->actingAs($this->outsider)->post(route('clubs.members.import', ['slug' => 'club-a']), ['csv_file' => $csv])->assertForbidden();
        $this->assertFalse(User::where('email', 'evil@example.test')->exists());

        $this->actingAs($this->member)->get(route('clubs.members.export', ['slug' => 'club-a']))->assertForbidden();
        $this->actingAs($this->outsider)->get(route('clubs.members.export', ['slug' => 'club-a']))->assertForbidden();
    }

    public function test_imported_members_do_not_get_a_known_password(): void
    {
        $csv = UploadedFile::fake()->createWithContent('m.csv', "name,email,role\nNewbie,newbie@example.test,member\n");

        $this->actingAs($this->admin)->post(route('clubs.members.import', ['slug' => 'club-a']), ['csv_file' => $csv]);

        $imported = User::where('email', 'newbie@example.test')->first();
        $this->assertNotNull($imported);
        $this->assertFalse(Hash::check('password123', $imported->password));
    }

    public function test_invoices_can_only_be_downloaded_by_their_owner_or_billing_staff(): void
    {
        $invoice = Invoice::create(['invoice_number' => 'INV-1', 'club_id' => $this->a->id, 'user_id' => $this->member->id, 'title' => 'Dues', 'amount' => 50, 'status' => 'sent']);
        $url = route('invoices.download', ['slug' => 'club-a', 'id' => $invoice->id, 'format' => 'html']);
        $otherMember = $this->join($this->a, 'member');

        $this->actingAs($otherMember)->get($url)->assertForbidden();
        $this->actingAs($this->outsider)->get($url)->assertForbidden();
        $this->actingAs($this->adminB)->get($url)->assertForbidden();
        $this->actingAs($this->member)->get($url)->assertOk();
        $this->actingAs($this->treasurer)->get($url)->assertOk();
    }

    public function test_committee_agenda_packs_need_access_to_that_club(): void
    {
        $meeting = ClubCommitteeMeeting::create(['club_id' => $this->a->id, 'title' => 'Committee', 'meeting_date' => now()->addWeek()]);

        foreach ([$this->outsider, $this->member, $this->adminB] as $user) {
            $this->actingAs($user)->get(route('committee.pack.pdf', ['meetingId' => $meeting->id]))->assertForbidden();
        }
    }

    public function test_member_portal_meeting_pages_require_membership_and_a_published_meeting(): void
    {
        $published = Meeting::create(['club_id' => $this->a->id, 'title' => 'Regular', 'meeting_date' => now()->addDays(9)->toDateString(), 'starts_at' => '19:00:00', 'venue' => 'Hall', 'dress_code' => 'Suit', 'status' => 'published']);
        $draft = Meeting::create(['club_id' => $this->a->id, 'title' => 'Draft', 'meeting_date' => now()->addDays(20)->toDateString(), 'starts_at' => '19:00:00', 'venue' => 'Hall', 'dress_code' => 'Suit', 'status' => 'draft']);

        $summons = fn (Meeting $m) => route('member.meetings.summons', ['slug' => 'club-a', 'id' => $m->id]);

        $this->actingAs($this->outsider)->get($summons($published))->assertStatus(403);
        $this->actingAs($this->outsider)->get(route('member.meetings.pdf', ['slug' => 'club-a', 'id' => $published->id]))->assertStatus(403);
        $this->actingAs($this->outsider)->post(route('member.meetings.rsvp', ['slug' => 'club-a', 'id' => $published->id]), ['attendance_status' => 'attending_dining']);
        $this->assertSame(0, MeetingRsvp::where('user_id', $this->outsider->id)->count());

        $this->actingAs($this->member)->get($summons($draft))->assertNotFound();
    }

    public function test_livewire_identifier_properties_are_locked(): void
    {
        $unlocked = [];

        foreach (glob(app_path('Domains/ClubAccounting/Livewire/**/*.php')) as $file) {
            $class = 'App\\Domains\\ClubAccounting\\Livewire\\'.str_replace(['/', '.php'], ['\\', ''], substr($file, strlen(app_path('Domains/ClubAccounting/Livewire/'))));
            if (! class_exists($class)) {
                continue;
            }

            foreach ((new ReflectionClass($class))->getProperties() as $property) {
                if ($property->isPublic() && in_array($property->getName(), ['clubSlug', 'clubId', 'meetingId', 'memberId'], true) && $property->getAttributes(Locked::class) === []) {
                    $unlocked[] = class_basename($class).'::$'.$property->getName();
                }
            }
        }

        $this->assertSame([], $unlocked, 'Client-editable identifiers: '.implode(', ', $unlocked));
    }

    public function test_the_contact_form_only_emails_the_club(): void
    {
        Mail::fake();

        $this->post(route('public.site.contact_form', ['clubSlug' => 'club-a']), [
            'email' => 'visitor@example.test', 'message' => 'Hello', 'recipient_email' => 'attacker@evil.test', 'cc_emails' => 'cc@evil.test',
        ]);

        Mail::assertNotSent(ContactFormSubmittedMail::class, fn ($mail) => $mail->hasTo('attacker@evil.test') || $mail->hasCc('cc@evil.test'));
    }

    public function test_login_and_api_token_attempts_are_rate_limited(): void
    {
        $last = null;
        for ($i = 0; $i < 12; $i++) {
            $last = $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'wrong'])->status();
        }
        $this->assertSame(429, $last, 'POST /login is not rate limited');

        $last = null;
        for ($i = 0; $i < 12; $i++) {
            $last = $this->postJson('/api/v1/tokens/create', ['email' => 'nobody@example.test', 'password' => 'wrong', 'device_name' => 'x'])->status();
        }
        $this->assertSame(429, $last, 'POST /api/v1/tokens/create is not rate limited');
    }

    public function test_an_admin_cannot_grant_or_change_the_owner_role(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.role.update', ['clubSlug' => 'club-a', 'userId' => $this->member->id]), ['role' => 'owner']);
        $this->assertSame('member', $this->a->users()->where('users.id', $this->member->id)->first()->pivot->role);

        $this->actingAs($this->admin)->post(route('admin.users.role.update', ['clubSlug' => 'club-a', 'userId' => $this->owner->id]), ['role' => 'member']);
        $this->assertSame('owner', $this->a->users()->where('users.id', $this->owner->id)->first()->pivot->role);
    }

    public function test_update_attachments_cannot_be_html_or_scripts(): void
    {
        $this->actingAs($this->admin)->post(route('admin.updates.store', ['clubSlug' => 'club-a']), [
            'title' => 'x', 'category' => 'general', 'status' => 'draft',
            'attachment_files' => [UploadedFile::fake()->create('evil.html', 5, 'text/html')],
        ])->assertSessionHasErrors();
    }

    public function test_admins_are_not_sent_other_peoples_invitation_tokens(): void
    {
        $this->a->users()->updateExistingPivot($this->pending->id, ['invitation_token' => 'SECRET-INVITE-TOKEN-XYZ']);

        $this->assertStringNotContainsString('SECRET-INVITE-TOKEN-XYZ', (string) $this->actingAs($this->admin)->get(route('admin.users.index', ['clubSlug' => 'club-a']))->getContent());
    }

    public function test_responses_carry_basic_security_headers(): void
    {
        $this->get('/login')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options')->assertHeader('Referrer-Policy')->assertHeader('Content-Security-Policy-Report-Only');
    }

    public function test_only_owners_can_change_who_holds_the_settings_capability(): void
    {
        $url = route('admin.settings.update', ['clubSlug' => 'club-a']);

        $this->actingAs($this->admin)->put($url, ['permission_matrix' => [
            'manage_settings' => ['roles' => ['owner', 'admin', 'member']],
            'manage_members' => ['roles' => ['owner', 'admin', 'treasurer']],
            'made_up' => ['roles' => ['member']],
        ]])->assertSessionHasNoErrors();

        $saved = $this->a->fresh()->settings['permission_matrix'];
        $this->assertArrayNotHasKey('manage_settings', $saved);
        $this->assertArrayNotHasKey('made_up', $saved);
        $this->assertSame(['owner', 'admin', 'treasurer'], $saved['manage_members']['roles']);

        $this->actingAs($this->owner)->put($url, ['permission_matrix' => ['manage_settings' => ['roles' => ['owner', 'admin', 'treasurer']]]]);
        $this->assertSame(['owner', 'admin', 'treasurer'], $this->a->fresh()->settings['permission_matrix']['manage_settings']['roles']);
    }

    public function test_settings_reject_script_urls_and_bad_domains(): void
    {
        $url = route('admin.settings.update', ['clubSlug' => 'club-a']);

        $this->actingAs($this->admin)->put($url, [
            'social_facebook' => 'javascript:alert(1)',
            'logo_url' => 'javascript:alert(1)',
            'custom_domain' => 'not a domain',
        ])->assertSessionHasErrors(['social_facebook', 'logo_url', 'custom_domain']);
    }

    public function test_a_custom_domain_cannot_be_claimed_by_two_clubs(): void
    {
        $this->b->update(['custom_domain' => 'taken.example.org']);

        $this->actingAs($this->admin)->put(route('admin.settings.update', ['clubSlug' => 'club-a']), ['custom_domain' => 'taken.example.org'])
            ->assertSessionHasErrors('custom_domain');
    }

    public function test_accounting_attachments_are_private_and_need_billing_access(): void
    {
        Storage::fake('local');

        $media = $this->a->addMedia(UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'))->toMediaCollection('accounting', 'local');
        $url = route('admin.accounting.attachments.show', ['clubSlug' => 'club-a', 'mediaId' => $media->id]);

        $this->assertSame('local', $media->disk);
        $this->actingAs($this->treasurer)->get($url)->assertOk();
        $this->actingAs($this->member)->get($url)->assertForbidden();
        $this->actingAs($this->adminB)->get($url)->assertForbidden();

        $otherClubUrl = route('admin.accounting.attachments.show', ['clubSlug' => 'club-b', 'mediaId' => $media->id]);
        $this->actingAs($this->adminB)->get($otherClubUrl)->assertNotFound();
    }

    public function test_gateway_secrets_are_encrypted_and_never_sent_back(): void
    {
        $this->actingAs($this->treasurer)->post(route('billing.business.update', ['clubSlug' => 'club-a']), [
            'business_name' => 'Club A',
            'billing_contact_email' => 'bill@club-a.test',
            'stripe_secret_key' => 'sk_live_SUPERSECRET',
            'stripe_webhook_secret' => 'whsec_SUPERSECRET',
        ]);

        $this->assertStringNotContainsString('SUPERSECRET', json_encode($this->a->fresh()->settings));
        $this->assertStringNotContainsString('SUPERSECRET', (string) $this->actingAs($this->treasurer)->get(route('billing.index', ['clubSlug' => 'club-a']))->getContent());

        $this->actingAs($this->treasurer)->post(route('billing.business.update', ['clubSlug' => 'club-a']), [
            'business_name' => 'Club A', 'billing_contact_email' => 'bill@club-a.test', 'stripe_secret_key' => '',
        ]);
        $this->assertNotEmpty($this->a->fresh()->settings['stripe_secret_key']);
    }

    public function test_check_in_only_accepts_members_of_the_club(): void
    {
        $event = Event::create(['club_id' => $this->a->id, 'title' => 'Dinner', 'slug' => 'dinner', 'starts_at' => now()->addDay(), 'status' => 'upcoming']);

        $this->actingAs($this->admin)->post(route('admin.events.checkin.store', ['clubSlug' => 'club-a', 'id' => $event->id]), ['user_id' => $this->outsider->id, 'action' => 'checkin'])->assertStatus(422);
        $this->assertDatabaseMissing('event_user', ['event_id' => $event->id, 'user_id' => $this->outsider->id]);
    }

    public function test_page_block_links_cannot_use_script_schemes(): void
    {
        $page = Page::create([
            'club_id' => $this->a->id, 'title' => 'Links', 'slug' => 'links-test', 'status' => 'published',
            'blocks' => [
                ['type' => 'hero', 'cta_link' => 'javascript:alert(1)'],
                ['type' => 'image', 'url' => ' JaVaScRiPt:alert(1)'],
                ['type' => 'button', 'url' => 'https://example.org/ok'],
                ['type' => 'button', 'url' => '/site/club-a/join-us'],
            ],
        ]);

        $blocks = $page->fresh()->blocks;
        $this->assertSame('', $blocks[0]['cta_link']);
        $this->assertSame('', $blocks[1]['url']);
        $this->assertSame('https://example.org/ok', $blocks[2]['url']);
        $this->assertSame('/site/club-a/join-us', $blocks[3]['url']);
    }

    public function test_roll_call_only_accepts_members_of_the_meetings_club(): void
    {
        $meeting = ClubCommitteeMeeting::create(['club_id' => $this->a->id, 'title' => 'Committee', 'meeting_date' => now()->addWeek()]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->admin)
            ->test(MeetingWorkspace::class, ['clubSlug' => 'club-a', 'meetingId' => $meeting->id])
            ->set('selectedUserId', $this->outsider->id)
            ->set('attendeeRole', 'Guest')
            ->call('addAttendee');
    }

    public function test_admins_cannot_read_or_touch_users_outside_their_club(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.show', ['clubSlug' => 'club-a', 'userId' => $this->outsider->id]))->assertNotFound();
        $this->actingAs($this->admin)->get(route('admin.users.show', ['clubSlug' => 'club-a', 'userId' => $this->adminB->id]))->assertNotFound();
        $this->actingAs($this->admin)->get(route('admin.users.show', ['clubSlug' => 'club-a', 'userId' => $this->member->id]))->assertOk();

        $meeting = Meeting::create(['club_id' => $this->a->id, 'title' => 'Regular', 'meeting_date' => now()->addDays(9)->toDateString(), 'starts_at' => '19:00:00', 'venue' => 'Hall', 'dress_code' => 'Suit', 'status' => 'published']);
        $this->actingAs($this->admin)->post(route('admin.meetings.rsvp.update', ['clubSlug' => 'club-a', 'id' => $meeting->id]), [
            'user_id' => $this->outsider->id, 'attendance_status' => 'apologies',
        ])->assertNotFound();
        $this->assertDatabaseMissing('meeting_rsvps', ['meeting_id' => $meeting->id, 'user_id' => $this->outsider->id]);
    }

    public function test_officer_rosters_cannot_name_members_of_another_club(): void
    {
        $foreign = Member::create(['club_id' => $this->b->id, 'first_name' => 'Other', 'last_name' => 'Club', 'membership_status' => 'active', 'current_office' => 'member']);

        $response = $this->actingAs($this->admin)->postJson(route('admin.officers.store', ['clubSlug' => 'club-a']), [
            'masonic_year' => '2026-2027',
            'assignments' => [['member_id' => $foreign->id, 'office' => 'wm']],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('club_acc_annual_officer_rosters', ['club_id' => $this->a->id, 'masonic_year' => '2026-2027']);
    }

    public function test_registration_needs_a_confirmed_email_and_does_not_reveal_existing_accounts(): void
    {
        config(['auth.require_email_verification' => true]);
        Notification::fake();
        $payload = ['name' => 'New Person', 'email' => 'new@example.test', 'password' => 'a-long-safe-passphrase', 'password_confirmation' => 'a-long-safe-passphrase'];

        $fresh = $this->post('/register', $payload);
        $fresh->assertRedirect(route('login'));
        $this->assertGuest();
        $user = User::where('email', 'new@example.test')->first();
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->post('/login', ['email' => 'new@example.test', 'password' => 'a-long-safe-passphrase'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $link = URL::signedRoute('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($link)->assertRedirect(route('login'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $this->post('/login', ['email' => 'new@example.test', 'password' => 'a-long-safe-passphrase'])->assertRedirect();
        $this->assertAuthenticated();

        auth()->logout();
        $existing = $this->post('/register', ['email' => $this->member->email] + $payload);
        $existing->assertRedirect(route('login'));
        $this->assertSame($fresh->getSession()->get('success'), $existing->getSession()->get('success'));
        $this->assertSame(1, User::where('email', $this->member->email)->count());
    }

    public function test_a_verification_link_needs_the_right_signature_and_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))->assertForbidden();
        $this->get(URL::signedRoute('verification.verify', ['id' => $user->id, 'hash' => 'wrong']))->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_visitor_sign_up_cannot_rename_an_existing_account(): void
    {
        $original = $this->member->name;

        $this->post(route('clubs.visitor.store', ['slug' => 'club-a']), [
            'name' => 'Hijacked', 'email' => $this->member->email, 'home_club_name' => 'Elsewhere',
        ]);

        $this->assertSame($original, $this->member->fresh()->name);
    }

    public function test_sign_up_stays_open_while_mail_is_not_configured(): void
    {
        config(['mail.default' => 'log', 'auth.require_email_verification' => null]);

        $this->post('/register', ['name' => 'No Mail', 'email' => 'nomail@example.test', 'password' => 'a-long-safe-passphrase', 'password_confirmation' => 'a-long-safe-passphrase'])->assertRedirect(route('login'));
        $this->assertTrue(User::where('email', 'nomail@example.test')->first()->hasVerifiedEmail());

        $this->post('/login', ['email' => 'nomail@example.test', 'password' => 'a-long-safe-passphrase'])->assertRedirect();
        $this->assertAuthenticated();

        config(['mail.default' => 'smtp']);
        $this->assertTrue(EmailVerification::required());
    }
}
