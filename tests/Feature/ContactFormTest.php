<?php

namespace Tests\Feature;

use App\Mail\ContactFormSubmittedMail;
use App\Models\Club;
use App\Models\ClubType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_contact_form_submits_successfully_and_sends_email(): void
    {
        Mail::fake();

        $clubType = ClubType::create(['name' => 'Lodge', 'code' => 'lodge']);
        $club = Club::create([
            'name' => 'Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'club_type_id' => $clubType->id,
            'email' => 'club@example.org',
            'settings' => [
                'contact_email' => 'secretary@lodgeoffraternity.org.uk',
            ],
        ]);

        $response = $this->post(route('public.site.contact_form', ['clubSlug' => $club->slug]), [
            'name' => 'Lord Edward Spencer',
            'email' => 'edward@example.com',
            'phone' => '+44 7123 456789',
            'message' => 'I would like to inquire about visiting your lodge.',
            'recipient_email' => 'secretary@lodgeoffraternity.org.uk',
            'cc_emails' => 'treasurer@lodgeoffraternity.org.uk, assistant@lodgeoffraternity.org.uk',
            'success_message' => 'Visit evil.example to claim your prize',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Thank you! Your message has been sent successfully.');

        Mail::assertQueued(ContactFormSubmittedMail::class, function ($mail) {
            return $mail->hasTo('secretary@lodgeoffraternity.org.uk')
                && $mail->hasCc('treasurer@lodgeoffraternity.org.uk')
                && $mail->hasCc('assistant@lodgeoffraternity.org.uk')
                && $mail->senderName === 'Lord Edward Spencer'
                && $mail->senderEmail === 'edward@example.com';
        });
    }

    public function test_contact_form_does_not_claim_success_when_the_lodge_has_no_address_to_send_to(): void
    {
        Mail::fake();

        $clubType = ClubType::create(['name' => 'Lodge', 'code' => 'lodge']);
        $club = Club::create(['name' => 'Lodge of Fraternity', 'slug' => 'lodge-of-fraternity', 'club_type_id' => $clubType->id]);

        $this->post(route('public.site.contact_form', ['clubSlug' => $club->slug]), [
            'name' => 'Lord Edward Spencer',
            'email' => 'edward@example.com',
            'message' => 'May I visit?',
            'recipient_email' => 'someone-else@example.org',
        ])->assertSessionHasErrors('message')->assertSessionMissing('success');

        Mail::assertNothingQueued();
    }

    public function test_contact_form_validates_email_address(): void
    {
        Mail::fake();

        $clubType = ClubType::create(['name' => 'Lodge', 'code' => 'lodge']);
        $club = Club::create([
            'name' => 'Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'club_type_id' => $clubType->id,
            'email' => 'club@example.org',
        ]);

        $response = $this->post(route('public.site.contact_form', ['clubSlug' => $club->slug]), [
            'name' => 'Test User',
            'email' => 'invalid-email-string',
            'message' => 'Hello',
        ]);

        $response->assertSessionHasErrors(['email']);
        Mail::assertNothingQueued();
    }

    public function test_robots_are_told_the_message_went_but_nothing_is_sent_and_one_sender_is_limited_per_hour(): void
    {
        Mail::fake();
        $clubType = ClubType::create(['name' => 'Lodge', 'code' => 'lodge']);
        $club = Club::create(['name' => 'Lodge of Fraternity', 'slug' => 'lodge-of-fraternity', 'club_type_id' => $clubType->id, 'email' => 'club@example.org']);
        $send = fn (array $data, string $ip = '10.0.0.1') => $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('public.site.contact_form', ['clubSlug' => $club->slug]), $data + ['name' => 'Pat', 'email' => 'pat@example.com', 'message' => 'Hello']);

        $send(['website' => 'http://spam.example', 'email' => 'bot@example.com'])->assertSessionHas('success');
        $send(['message' => 'Buy https://a.example https://b.example www.c.example', 'email' => 'bot@example.com'], '10.0.0.2')->assertSessionHas('success');
        Mail::assertNothingQueued();

        foreach (range(1, 5) as $n) {
            $send([], "10.0.1.{$n}")->assertSessionHas('success');
        }
        Mail::assertQueuedCount(5);
        $send([], '10.0.2.1')->assertTooManyRequests();
    }
}
