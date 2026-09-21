<?php

namespace Tests\Feature;

use App\Jobs\SendNewsletterDelivery;
use App\Mail\NewsletterMail;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\ClubUpdate;
use App\Models\Newsletter;
use App\Models\NewsletterDelivery;
use App\Models\NewsletterSubscription;
use App\Models\NewsletterType;
use App\Models\User;
use App\Services\Newsletters\NewsletterSender;
use App\Services\WeeklyUpdateDigestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class NewsletterSendingTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $admin;

    private NewsletterType $general;

    private NewsletterType $summons;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active', 'settings' => ['email_from_name' => 'Lodge Secretary', 'email_reply_to' => 'secretary@club-a.test']]);
        $this->admin = $this->join($this->club, 'admin');
        $this->general = NewsletterType::create(['club_id' => $this->club->id, 'name' => 'General News', 'slug' => 'general', 'is_external_subscribable' => true, 'is_mandatory' => false]);
        $this->summons = NewsletterType::create(['club_id' => $this->club->id, 'name' => 'Summonses', 'slug' => 'summons', 'is_external_subscribable' => true, 'is_mandatory' => true]);
    }

    private function join(Club $club, string $role, string $status = 'active', array $user = []): User
    {
        $created = User::factory()->create($user);
        $club->users()->attach($created->id, ['role' => $role, 'status' => $status]);

        return $created;
    }

    private function subscriber(NewsletterType $type, string $email, string $status = 'active', ?int $userId = null): NewsletterSubscription
    {
        return NewsletterSubscription::create(['club_id' => $type->club_id, 'newsletter_type_id' => $type->id, 'email' => $email, 'name' => 'Visitor '.$email, 'status' => $status, 'user_id' => $userId, 'subscribed_at' => now()]);
    }

    private function newsletter(NewsletterType $type, array $extra = []): Newsletter
    {
        return Newsletter::create($extra + ['club_id' => $this->club->id, 'newsletter_type_id' => $type->id, 'subject' => 'Summer news for {{first_name}}', 'content' => '<p>Hello {{first_name}} from {{club_name}}</p>', 'target_roles' => ['member'], 'status' => 'draft']);
    }

    private function send(Newsletter $newsletter)
    {
        return $this->actingAs($this->admin)->post(route('admin.newsletters.send', ['clubSlug' => 'club-a', 'id' => $newsletter->id]));
    }

    /**
     * @return list<string>
     */
    private function recipients(Newsletter $newsletter): array
    {
        return $newsletter->deliveries()->orderBy('email')->pluck('email')->all();
    }

    public function test_a_newsletter_goes_to_the_targeted_roles_and_active_visitors_once_each_and_to_nobody_else(): void
    {
        Mail::fake();
        $member = $this->join($this->club, 'member', 'active', ['email' => 'member@example.test']);
        $this->join($this->club, 'coach', 'active', ['email' => 'coach@example.test']);
        $this->join($this->club, 'member', 'pending', ['email' => 'pending@example.test']);
        $this->subscriber($this->general, 'visitor@example.test');
        $this->subscriber($this->general, 'waiting@example.test', 'pending_approval');
        $this->subscriber($this->general, 'left@example.test', 'unsubscribed');
        $this->subscriber($this->general, 'rejected@example.test', 'rejected');
        $this->subscriber($this->general, 'MEMBER@example.test');
        $otherClub = Club::create(['club_type_id' => ClubType::first()->id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']);
        $this->join($otherClub, 'member', 'active', ['email' => 'elsewhere@example.test']);
        $newsletter = $this->newsletter($this->general);

        $this->send($newsletter)->assertSessionHasNoErrors();

        $this->assertSame(['member@example.test', 'visitor@example.test'], $this->recipients($newsletter));
        Mail::assertSent(NewsletterMail::class, 2);
        Mail::assertSent(NewsletterMail::class, fn ($mail) => $mail->hasTo('member@example.test'));
        Mail::assertSent(NewsletterMail::class, fn ($mail) => $mail->hasTo('visitor@example.test'));
        $this->assertSame(2, $newsletter->deliveries()->where('status', 'sent')->count());
        $this->assertSame($member->id, $newsletter->deliveries()->where('email', 'member@example.test')->value('user_id'));
        $this->assertSame('sent', $newsletter->fresh()->status);
    }

    public function test_members_can_opt_out_of_an_optional_channel_but_not_a_mandatory_one(): void
    {
        Mail::fake();
        $member = $this->join($this->club, 'member', 'active', ['email' => 'member@example.test']);
        $this->join($this->club, 'member', 'active', ['email' => 'other@example.test']);
        $this->subscriber($this->general, 'member@example.test', 'unsubscribed', $member->id);
        $this->subscriber($this->summons, 'member@example.test', 'unsubscribed', $member->id);

        $optional = $this->newsletter($this->general);
        $official = $this->newsletter($this->summons);
        $this->send($optional);
        $this->send($official);

        $this->assertSame(['other@example.test'], $this->recipients($optional));
        $this->assertSame(['member@example.test', 'other@example.test'], $this->recipients($official));
    }

    public function test_a_sent_newsletter_cannot_be_sent_or_changed_again_so_nobody_gets_it_twice(): void
    {
        Mail::fake();
        $this->join($this->club, 'member', 'active', ['email' => 'member@example.test']);
        $newsletter = $this->newsletter($this->general);

        $this->send($newsletter);
        $this->send($newsletter)->assertSessionHasErrors('status');
        $this->actingAs($this->admin)->post(route('admin.newsletters.store', ['clubSlug' => 'club-a']), ['id' => $newsletter->id, 'newsletter_type_id' => $this->general->id, 'subject' => 'Changed', 'content' => '<p>x</p>', 'target_roles' => ['member'], 'status' => 'sent'])->assertSessionHasErrors('status');

        Mail::assertSent(NewsletterMail::class, 1);
        $this->assertSame('Summer news for {{first_name}}', $newsletter->fresh()->subject);

        $this->actingAs($this->admin)->post(route('admin.newsletters.duplicate', ['clubSlug' => 'club-a', 'id' => $newsletter->id]))->assertRedirect();
        $copy = Newsletter::where('id', '!=', $newsletter->id)->sole();
        $this->assertSame('draft', $copy->status);
        $this->assertSame(0, $copy->deliveries()->count());
    }

    public function test_the_composer_saves_a_draft_and_sends_it_in_one_step(): void
    {
        Mail::fake();
        $this->join($this->club, 'member', 'active', ['email' => 'member@example.test']);
        $payload = ['newsletter_type_id' => $this->general->id, 'subject' => 'Hello', 'content' => '<p>Body</p><script>alert(1)</script>', 'target_roles' => ['member']];

        $this->actingAs($this->admin)->post(route('admin.newsletters.store', ['clubSlug' => 'club-a']), $payload + ['status' => 'draft'])->assertSessionHasNoErrors();
        Mail::assertNothingSent();
        $this->assertSame('draft', Newsletter::sole()->status);
        $this->assertStringNotContainsString('script', Newsletter::sole()->content);

        $this->actingAs($this->admin)->post(route('admin.newsletters.store', ['clubSlug' => 'club-a']), $payload + ['status' => 'sent', 'id' => Newsletter::sole()->id])->assertSessionHasNoErrors();
        Mail::assertSent(NewsletterMail::class, 1);
    }

    public function test_a_failed_delivery_is_recorded_and_only_the_failed_ones_are_retried(): void
    {
        Mail::fake();
        $this->join($this->club, 'member', 'active', ['email' => 'a@example.test']);
        $this->join($this->club, 'member', 'active', ['email' => 'b@example.test']);
        $newsletter = $this->newsletter($this->general, ['status' => 'sent', 'sent_at' => now()]);
        app(NewsletterSender::class)->queueDeliveries($newsletter);
        Mail::assertSent(NewsletterMail::class, 2);

        $failing = $newsletter->deliveries()->where('email', 'b@example.test')->first();
        (new SendNewsletterDelivery($failing->id))->failed(new RuntimeException('Mailbox unavailable'));
        $this->assertSame('failed', $failing->fresh()->status);
        $this->assertSame('Mailbox unavailable', $failing->fresh()->error);

        $this->actingAs($this->admin)->post(route('admin.newsletters.retry', ['clubSlug' => 'club-a', 'id' => $newsletter->id]))->assertSessionHas('success', fn ($m) => str_contains($m, 'Retrying 1'));

        Mail::assertSent(NewsletterMail::class, 3);
        $this->assertSame('sent', $failing->fresh()->status);
        $this->assertSame(2, $newsletter->deliveries()->count(), 'no extra deliveries');

        // Running the same job again never sends twice.
        (new SendNewsletterDelivery($failing->id))->handle();
        Mail::assertSent(NewsletterMail::class, 3);
    }

    public function test_the_unsubscribe_link_works_without_logging_in_and_is_honoured_by_the_next_send(): void
    {
        Mail::fake();
        $visitor = $this->subscriber($this->general, 'visitor@example.test');
        $first = $this->newsletter($this->general, ['target_roles' => ['member']]);
        $this->send($first);
        $token = $first->deliveries()->sole()->token;
        $url = route('newsletters.unsubscribe', ['token' => $token]);

        $this->get($url)->assertOk()->assertSee('visitor@example.test')->assertSee('Yes, unsubscribe me');
        $this->post($url)->assertOk()->assertSee('You have been unsubscribed');
        $this->assertSame('unsubscribed', $visitor->fresh()->status);

        $second = $this->newsletter($this->general);
        $this->send($second);
        $this->assertSame([], $this->recipients($second));

        $this->post($url, ['resubscribe' => 1])->assertOk()->assertSee('subscribed again');
        $this->assertSame('active', $visitor->fresh()->status);

        $this->get(route('newsletters.unsubscribe', ['token' => str_repeat('x', 40)]))->assertNotFound();
        $this->get(route('newsletters.unsubscribe', ['token' => 'short']))->assertNotFound();
    }

    public function test_a_member_can_leave_an_optional_channel_from_the_link_but_not_official_notices(): void
    {
        Mail::fake();
        $member = $this->join($this->club, 'member', 'active', ['email' => 'member@example.test']);
        $optional = $this->newsletter($this->general);
        $official = $this->newsletter($this->summons);
        $this->send($optional);
        $this->send($official);

        $this->post(route('newsletters.unsubscribe', ['token' => $official->deliveries()->sole()->token]))->assertOk()->assertSee('Official lodge notices');
        $this->assertSame(0, NewsletterSubscription::where('user_id', $member->id)->count());

        $this->post(route('newsletters.unsubscribe', ['token' => $optional->deliveries()->sole()->token]))->assertOk()->assertSee('You have been unsubscribed');
        $this->assertSame('unsubscribed', NewsletterSubscription::where('user_id', $member->id)->where('newsletter_type_id', $this->general->id)->value('status'));
    }

    public function test_the_email_is_personal_branded_and_carries_the_unsubscribe_headers(): void
    {
        $member = $this->join($this->club, 'member', 'active', ['name' => 'Alan <b>Archer</b>', 'email' => 'alan@example.test']);
        $this->general->update(['sender_name' => 'Events Team', 'sender_email' => 'events@club-a.test']);
        $newsletter = $this->newsletter($this->general, ['attachments' => [['name' => 'Agenda.pdf', 'url' => '/storage/1/agenda.pdf']]]);
        $delivery = NewsletterDelivery::create(['newsletter_id' => $newsletter->id, 'club_id' => $this->club->id, 'email' => 'alan@example.test', 'name' => $member->name, 'user_id' => $member->id, 'token' => str_repeat('a', 40)]);

        $mail = NewsletterMail::forDelivery($delivery->load('newsletter.club', 'newsletter.newsletterType'));
        $html = $mail->render();

        $this->assertSame('Summer news for Alan', $mail->envelope()->subject);
        $this->assertStringContainsString('Hello Alan from Club A', $html);
        $this->assertStringNotContainsString('<b>Archer</b>', $html);
        $this->assertStringContainsString('http://', $html);
        $this->assertStringContainsString(url('/storage/1/agenda.pdf'), $html, 'attachment links are absolute');
        $this->assertStringContainsString(route('newsletters.unsubscribe', ['token' => str_repeat('a', 40)]), $html);
        $this->assertStringContainsString('View in your browser', $html);

        $envelope = $mail->envelope();
        $this->assertSame('Events Team', $envelope->from->name);
        $this->assertSame('events@club-a.test', $envelope->replyTo[0]->address);

        $headers = $mail->headers()->text;
        $this->assertStringContainsString('/webhooks/newsletters/unsubscribe/', $headers['List-Unsubscribe']);
        $this->assertSame('List-Unsubscribe=One-Click', $headers['List-Unsubscribe-Post']);

        $this->general->update(['sender_name' => null, 'sender_email' => null]);
        $fallback = NewsletterMail::forDelivery($delivery->fresh()->load('newsletter.club', 'newsletter.newsletterType'))->envelope();
        $this->assertSame('Lodge Secretary', $fallback->from->name);
        $this->assertSame('secretary@club-a.test', $fallback->replyTo[0]->address);
    }

    public function test_the_browser_copy_shows_the_newsletter_without_an_unsubscribe_link_and_needs_the_private_token(): void
    {
        $newsletter = $this->newsletter($this->general);
        $delivery = NewsletterDelivery::create(['newsletter_id' => $newsletter->id, 'club_id' => $this->club->id, 'email' => 'x@example.test', 'name' => 'Xena Warrior', 'token' => str_repeat('b', 40)]);

        $this->get(route('newsletters.view', ['token' => $delivery->token]))->assertOk()->assertSee('Hello Xena from Club A', false)->assertDontSee('Unsubscribe');
        $this->get(route('newsletters.view', ['token' => str_repeat('c', 40)]))->assertNotFound();
    }

    public function test_the_composer_previews_and_sends_tests_only_to_the_person_asking_and_only_organisers_may(): void
    {
        $payload = ['subject' => 'Draft for {{first_name}}', 'content' => '<p>Hi {{first_name}} <script>x()</script></p>', 'newsletter_type_id' => $this->general->id];

        $html = $this->actingAs($this->admin)->postJson(route('admin.newsletters.preview', ['clubSlug' => 'club-a']), $payload)->assertOk()->json('html');
        $this->assertStringContainsString('Hi Alex', $html);
        $this->assertStringNotContainsString('<script>', $html);

        $this->actingAs($this->admin)->post(route('admin.newsletters.test', ['clubSlug' => 'club-a']), $payload)->assertSessionHas('success');
        $messages = app('mail.manager')->mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $message = $messages->first()->getOriginalMessage();
        $this->assertStringStartsWith('[Test] Draft for ', $message->getSubject());
        $this->assertSame([$this->admin->email], array_map(fn ($a) => $a->getAddress(), $message->getTo()));
        $this->assertSame(0, NewsletterDelivery::count());
        $this->assertSame(0, Newsletter::count());

        $outsider = $this->join(Club::create(['club_type_id' => ClubType::first()->id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']), 'admin');
        foreach ([$this->join($this->club, 'member'), $outsider] as $user) {
            $this->actingAs($user)->postJson(route('admin.newsletters.preview', ['clubSlug' => 'club-a']), $payload)->assertForbidden();
            $this->actingAs($user)->post(route('admin.newsletters.test', ['clubSlug' => 'club-a']), $payload)->assertForbidden();
            $this->actingAs($user)->post(route('admin.newsletters.retry', ['clubSlug' => 'club-a', 'id' => 1]))->assertForbidden();
        }
    }

    public function test_a_members_first_change_in_the_portal_switches_a_channel_off_and_official_notices_stay_on(): void
    {
        $member = $this->join($this->club, 'member', 'active', ['email' => 'member@example.test']);

        $this->actingAs($member)->post(route('portal.subscriptions.toggle', ['clubSlug' => 'club-a', 'typeId' => $this->general->id]))->assertSessionHasNoErrors();
        $this->assertSame('unsubscribed', NewsletterSubscription::where('user_id', $member->id)->value('status'));

        $this->actingAs($member)->post(route('portal.subscriptions.toggle', ['clubSlug' => 'club-a', 'typeId' => $this->summons->id]))->assertSessionHasErrors('channel');
        $this->assertSame(1, NewsletterSubscription::where('user_id', $member->id)->count());

        $this->actingAs($member)->get(route('portal.subscriptions'))->assertInertia(fn ($page) => $page->where('clubMatrix.0.channels', fn ($channels) => collect($channels)->firstWhere('name', 'General News')['is_active'] === false && collect($channels)->firstWhere('name', 'Summonses')['is_active'] === true));
    }

    public function test_the_weekly_digest_is_emailed_to_the_members_of_its_channel(): void
    {
        Mail::fake();
        $this->join($this->club, 'member', 'active', ['email' => 'member@example.test']);
        ClubUpdate::create(['club_id' => $this->club->id, 'author_id' => $this->admin->id, 'title' => 'Provincial bulletin', 'category' => 'provincial', 'summary' => 'News.', 'status' => 'approved']);

        $this->artisan('app:send-weekly-digest', ['--club' => 'club-a'])->assertExitCode(0);

        Mail::assertSent(NewsletterMail::class, fn ($mail) => $mail->hasTo('member@example.test') && str_contains($mail->render(), 'Provincial bulletin'));
        $this->assertSame('sent', Newsletter::sole()->status);
    }

    public function test_a_digest_channel_is_due_on_its_day_and_hour_and_not_again_before_its_frequency_has_passed(): void
    {
        $service = new WeeklyUpdateDigestService;
        $digest = NewsletterType::create(['club_id' => $this->club->id, 'name' => 'Digest', 'slug' => 'digest', 'is_automated_digest' => true, 'digest_frequency' => 'weekly', 'digest_send_day' => 'friday', 'digest_send_time' => '09:00']);
        $friday = Carbon::parse('2026-09-25 09:30');

        $this->assertTrue($service->isDue($digest, $friday));
        $this->assertFalse($service->isDue($digest, Carbon::parse('2026-09-24 09:30')), 'wrong day');
        $this->assertFalse($service->isDue($digest, Carbon::parse('2026-09-25 10:05')), 'wrong hour');
        $this->assertFalse($service->isDue($this->general, $friday), 'not an automated digest');

        Newsletter::create(['club_id' => $this->club->id, 'newsletter_type_id' => $digest->id, 'subject' => 'Last', 'content' => '<p>x</p>', 'status' => 'sent', 'sent_at' => $friday->copy()->subDays(3)]);
        $this->assertFalse($service->isDue($digest, $friday), 'sent three days ago');

        Newsletter::query()->update(['sent_at' => $friday->copy()->subDays(7)]);
        $this->assertTrue($service->isDue($digest, $friday));

        $digest->update(['digest_frequency' => 'monthly']);
        $this->assertFalse($service->isDue($digest, $friday), 'monthly digests wait about four weeks');
    }
}
