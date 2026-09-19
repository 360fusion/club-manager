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
            'success_message' => 'Thank you! Your inquiry has been sent.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Thank you! Your inquiry has been sent.');

        Mail::assertSent(ContactFormSubmittedMail::class, function ($mail) {
            return $mail->hasTo('secretary@lodgeoffraternity.org.uk')
                && $mail->hasCc('treasurer@lodgeoffraternity.org.uk')
                && $mail->hasCc('assistant@lodgeoffraternity.org.uk')
                && $mail->senderName === 'Lord Edward Spencer'
                && $mail->senderEmail === 'edward@example.com';
        });
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
        Mail::assertNothingSent();
    }
}
