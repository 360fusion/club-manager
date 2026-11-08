<?php

namespace Tests\Feature\Launchpad;

use App\Enums\Visibility;
use App\Events\SignatureCompleted;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\Donation;
use App\Models\DonationContribution;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Invoice;
use App\Models\Newsletter;
use App\Models\NewsletterType;
use App\Models\Post;
use App\Models\SignatureRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Launchpad\Models\WebhookDelivery;
use Launchpad\Models\WebhookEndpoint;
use Launchpad\Models\WorkspaceEvent;
use Launchpad\Webhooks\DeliverWebhook;
use Launchpad\Webhooks\Webhooks;
use Tests\TestCase;

/** Events, bookings, invoices, donations, newsletters, posts and signatures go in the club's activity log (and to its webhooks), once each */
class ClubOtherEventsTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
    }

    public function test_an_event_is_recorded_when_it_leaves_draft(): void
    {
        $event = $this->event('draft');
        $event->update(['description' => 'More words']);
        $this->assertSame([], $this->lines());

        $event->update(['status' => 'upcoming']);
        $event->update(['status' => 'completed']);
        $this->assertSame(['event.published'], $this->lines());
    }

    public function test_an_event_made_live_is_recorded_once(): void
    {
        $this->event('upcoming');

        $this->assertSame(['event.published'], $this->lines());
    }

    public function test_a_booking_is_recorded_when_made_and_when_paid(): void
    {
        $registration = EventRegistration::create(['event_id' => $this->event('draft')->id, 'contact_name' => 'Bea Booker', 'status' => 'attending', 'total' => 40]);
        $registration->update(['notes' => 'Window seat']);
        $registration->update(['payment_status' => 'paid', 'amount_paid' => 40]);
        $registration->update(['internal_note' => 'Checked']);

        $this->assertSame(['event.registration.created', 'event.registration.paid'], $this->lines());
        $this->assertStringContainsString('Bea Booker', WorkspaceEvent::withoutGlobalScopes()->orderBy('id')->first()->summary);
    }

    public function test_an_invoice_is_recorded_when_raised_and_when_paid(): void
    {
        $invoice = $this->invoice('unpaid');
        $invoice->update(['notes' => 'Chased']);
        $invoice->update(['status' => 'paid']);

        $this->assertSame(['invoice.created', 'invoice.paid'], $this->lines());
    }

    /** Draft and pending-approval invoices are not raised yet */
    public function test_a_draft_invoice_is_announced_when_issued(): void
    {
        $invoice = $this->invoice('draft');
        $invoice->update(['notes' => 'Check the amount']);
        $invoice->update(['status' => 'pending_approval']);
        $this->assertSame([], $this->lines());

        $invoice->update(['status' => 'unpaid']);
        $invoice->update(['status' => 'paid']);
        $this->assertSame(['invoice.created', 'invoice.paid'], $this->lines());
    }

    public function test_an_invoice_raised_paid_is_recorded_as_paid_once(): void
    {
        $this->invoice('paid');

        $this->assertSame(['invoice.created', 'invoice.paid'], $this->lines());
    }

    public function test_a_donation_is_recorded_when_paid(): void
    {
        $donation = Donation::create(['club_id' => $this->club->id, 'campaign_name' => 'Roof', 'target_amount' => 1000]);
        DonationContribution::create(['donation_id' => $donation->id, 'donor_name' => 'Dan Donor', 'donor_email' => 'dan@example.test', 'amount' => 25, 'payment_status' => 'paid']);
        $pending = DonationContribution::create(['donation_id' => $donation->id, 'donor_name' => 'Pat Pending', 'donor_email' => 'pat@example.test', 'amount' => 10, 'payment_status' => 'pending']);
        $pending->update(['donor_name' => 'Pat Paid']);
        $this->assertSame(['donation.received'], $this->lines());

        $pending->update(['payment_status' => 'paid']);
        $this->assertSame(['donation.received', 'donation.received'], $this->lines());
    }

    public function test_a_newsletter_is_recorded_when_sent(): void
    {
        $newsletter = $this->newsletter('draft');
        $newsletter->update(['subject' => 'Autumn news']);
        $this->assertSame([], $this->lines());

        $newsletter->update(['status' => 'sent', 'sent_at' => now()]);
        $this->newsletter('sent');
        $this->assertSame(['newsletter.sent', 'newsletter.sent'], $this->lines());
    }

    public function test_a_post_is_recorded_when_published(): void
    {
        $draft = $this->newPost('draft', 'Draft post');
        $draft->update(['excerpt' => 'Short']);
        $this->assertSame([], $this->lines());

        $draft->update(['status' => 'published']);
        $this->newPost('published', 'Straight out')->update(['excerpt' => 'Edited']);
        $this->assertSame(['post.published', 'post.published'], $this->lines());
    }

    /** Members cannot see a post before its published_at, so it is not announced then */
    public function test_a_scheduled_post_is_not_announced_at_save(): void
    {
        $this->newPost('published', 'Later')->update(['published_at' => now()->addWeek()]);
        Post::create(['club_id' => $this->club->id, 'author_id' => User::factory()->create()->id, 'title' => 'Next week', 'slug' => 'next-week', 'content' => '<p>Body</p>', 'status' => 'published', 'published_at' => now()->addWeek()]);

        $this->assertSame(['post.published'], $this->lines());
    }

    public function test_a_completed_signature_is_recorded(): void
    {
        $signer = User::factory()->create(['name' => 'Sid Signer']);
        $request = SignatureRequest::create([
            'club_id' => $this->club->id, 'signable_type' => User::class, 'signable_id' => $signer->id, 'purpose' => 'form_p_proposer',
            'signer_type' => User::class, 'signer_id' => $signer->id, 'signer_name' => 'Sid Signer', 'token_hash' => 'x', 'requested_at' => now(),
        ]);

        event(new SignatureCompleted($request));

        $this->assertSame(['signature.completed'], $this->lines());
        $this->assertStringContainsString('Sid Signer', WorkspaceEvent::withoutGlobalScopes()->sole()->summary);
    }

    public function test_a_subscribed_endpoint_is_sent_only_what_it_asked_for(): void
    {
        Queue::fake();
        WebhookEndpoint::create(['workspace_id' => $this->club->id, 'url' => 'https://hooks.example.com/x', 'secret' => Webhooks::newSecret(), 'events' => ['invoice.paid'], 'active' => true]);

        $this->invoice('paid');

        Queue::assertPushed(DeliverWebhook::class, 1);
        $delivery = WebhookDelivery::sole();
        $this->assertSame('invoice.paid', $delivery->event);
        $this->assertSame('INV-1', $delivery->payload['invoice_number']);
    }

    private function event(string $status): Event
    {
        return Event::create(['club_id' => $this->club->id, 'title' => 'Ladies Night', 'slug' => 'ladies-night-'.$status, 'starts_at' => now()->addDays(30), 'status' => $status, 'visibility' => Visibility::Club]);
    }

    private function invoice(string $status): Invoice
    {
        return Invoice::create(['club_id' => $this->club->id, 'user_id' => User::factory()->create()->id, 'invoice_number' => 'INV-1', 'title' => 'Dues', 'amount' => 50, 'status' => $status]);
    }

    private function newsletter(string $status): Newsletter
    {
        $type = NewsletterType::firstOrCreate(['club_id' => $this->club->id, 'name' => 'General', 'slug' => 'general']);

        return Newsletter::create(['club_id' => $this->club->id, 'newsletter_type_id' => $type->id, 'subject' => 'Summer news', 'content' => '<p>Hello</p>', 'status' => $status]);
    }

    private function newPost(string $status, string $title): Post
    {
        return Post::create(['club_id' => $this->club->id, 'author_id' => User::factory()->create()->id, 'title' => $title, 'slug' => str($title)->slug()->toString(), 'content' => '<p>Body</p>', 'status' => $status]);
    }

    /** @return list<string> */
    private function lines(): array
    {
        return WorkspaceEvent::withoutGlobalScopes()->where('workspace_id', $this->club->id)->orderBy('id')->pluck('action')->all();
    }
}
