<?php

namespace App\Http\Controllers;

use App\Mail\NewsletterMail;
use App\Models\NewsletterDelivery;
use App\Services\Newsletters\NewsletterOptOut;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The links inside a newsletter email, opened by the recipient without logging in: unsubscribe (also the one-click
 * address mail programs post to) and "view in your browser". The token in the address is private to one person.
 */
class NewsletterPublicController extends Controller
{
    public function unsubscribeForm(string $token, NewsletterOptOut $optOut): Response
    {
        $delivery = $this->find($token);

        return response()->view('newsletters.unsubscribe', ['delivery' => $delivery, 'club' => $delivery->newsletter->club, 'channel' => $delivery->newsletter->newsletterType, 'canOptOut' => $optOut->canOptOut($delivery), 'done' => false]);
    }

    /**
     * Also answers the one-click POST that Gmail and others send (RFC 8058), which has no page and no CSRF token.
     */
    public function unsubscribe(Request $request, string $token, NewsletterOptOut $optOut): Response
    {
        $delivery = $this->find($token);

        if ($request->boolean('resubscribe')) {
            $optOut->resubscribe($delivery);

            return response()->view('newsletters.unsubscribe', ['delivery' => $delivery, 'club' => $delivery->newsletter->club, 'channel' => $delivery->newsletter->newsletterType, 'canOptOut' => true, 'done' => false, 'resubscribed' => true]);
        }

        $done = $optOut->apply($delivery);

        return response()->view('newsletters.unsubscribe', ['delivery' => $delivery, 'club' => $delivery->newsletter->club, 'channel' => $delivery->newsletter->newsletterType, 'canOptOut' => $optOut->canOptOut($delivery), 'done' => $done]);
    }

    public function view(string $token): Response
    {
        $delivery = $this->find($token);
        $mail = NewsletterMail::forDelivery($delivery);

        return response()->view('emails.newsletter', $mail->viewData(web: true));
    }

    private function find(string $token): NewsletterDelivery
    {
        abort_if(strlen($token) !== 40, 404);

        return NewsletterDelivery::with('newsletter.club', 'newsletter.newsletterType')->where('token', $token)->firstOrFail();
    }
}
