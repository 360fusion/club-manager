<?php

namespace Launchpad\Http\Controllers\Site;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Launchpad\Accounts\AccountRegistration;
use Launchpad\Accounts\Turnstile;
use Launchpad\Http\Ui;
use Launchpad\Models\ContactMessage;
use Launchpad\Notifications\ContactMessageReceived;
use Launchpad\Notifications\ContactReceipt;
use Launchpad\Site\Site;

class ContactController extends Controller
{
    public function show(Request $request): View|Responsable
    {
        return Ui::render('site.contact', [
            'topics' => (array) config('launchpad.contact.topics'),
            'turnstileKey' => Turnstile::siteKey(),
            'user' => $request->user(),
        ], fn () => [
            'topics' => array_values((array) config('launchpad.contact.topics')),
            'turnstileKey' => Turnstile::siteKey(),
            'user' => $request->user() ? ['name' => $request->user()->name, 'email' => $request->user()->email] : null,
            'sent' => $request->session()->get('sent'),
        ]);
    }

    public function store(Request $request, Turnstile $turnstile): RedirectResponse
    {
        $turnstile->verify($request);

        $request->merge([
            'name' => AccountRegistration::cleanName($request->input('name')),
            'email' => AccountRegistration::normalizeEmail($request->input('email')),
        ]);

        $topics = (array) config('launchpad.contact.topics');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'topic' => ['nullable', 'string', 'in:'.implode(',', $topics)],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        // a hidden field only a script fills in, or a message that is mostly links: answered as if it worked, and dropped
        if ($request->filled('website') || preg_match_all('~https?://~i', $data['message']) > 2) {
            return redirect()->route('contact')->with('sent', 'CM-'.strtoupper(substr(sha1(uniqid()), 0, 8)));
        }

        $message = ContactMessage::create($data + [
            'reference' => ContactMessage::newReference(),
            'user_id' => $request->user()?->getKey(),
            'message' => strip_tags($data['message']),
        ]);

        if ($support = Site::get('support_email')) {
            Notification::route('mail', $support)->notify(new ContactMessageReceived($message));
        }
        Notification::route('mail', $message->email)->notify(new ContactReceipt($message));

        return redirect()->route('contact')->with('sent', $message->reference);
    }
}
