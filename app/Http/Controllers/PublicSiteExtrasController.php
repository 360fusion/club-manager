<?php

namespace App\Http\Controllers;

use App\Enums\ClubMemberStatus;
use App\Http\Requests\PublicSite\SubmitContactFormRequest;
use App\Mail\ContactFormSubmittedMail;
use App\Models\Media;
use App\Models\Page;
use App\Support\ClubAccess;
use App\Support\IcsCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The public site's non-page endpoints: a club's downloadable files, its calendar feed and the contact form. Split out of PublicSiteController; the URLs, route names and permissions are unchanged.
 */
class PublicSiteExtrasController extends Controller
{
    /**
     * A file uploaded to a Downloads block. It lives on the private disk, so this is the only way to reach it:
     * it must be listed in a downloads block on a published page of this club, and a members-only block (or a
     * members-only page) is only served to an active member.
     */
    public function file(string $clubSlug, int $mediaId): BinaryFileResponse|RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        /** @var Media|null $media */
        $media = $club->media()->where('collection_name', 'page_downloads')->find($mediaId);
        abort_unless($media, 404);

        $found = null;

        foreach (Page::where('club_id', $club->id)->live()->get() as $page) {
            foreach ($page->blocks ?? [] as $block) {
                if (($block['type'] ?? null) !== 'downloads') {
                    continue;
                }

                foreach ($block['items'] ?? [] as $item) {
                    if (($item['source'] ?? null) === 'upload' && (int) ($item['media_id'] ?? 0) === $media->id) {
                        // A file listed in both a public and a members-only place counts as members-only.
                        $restricted = ! empty($block['members_only']) || $page->is_members_only;
                        if ($found === null || ($restricted && ! $found['restricted'])) {
                            $found = ['restricted' => $restricted, 'inline' => ($block['open_in'] ?? 'new_tab') !== 'download'];
                        }
                    }
                }
            }
        }

        abort_unless($found, 404);

        if ($found['restricted'] && ! ClubAccess::isActiveMember(Auth::user(), $club)) {
            return redirect()->guest(route('login'))->with('error', 'That document is for members only.');
        }

        // Only formats a browser shows safely are opened in the tab; everything else is downloaded.
        $showsInBrowser = in_array(strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION)), ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt'], true);
        $disposition = $found['inline'] && $showsInBrowser ? 'inline' : 'attachment';

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => $disposition.'; filename="'.str_replace('"', '', $media->file_name).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * A public calendar of the club's public events, for the calendar block's "Add to your calendar" link.
     */
    public function calendarFeed(string $clubSlug): Response
    {
        $club = $this->findClub($clubSlug);

        $ics = new IcsCalendar($club->name, parse_url(config('app.url'), PHP_URL_HOST) ?: 'clubmanager');
        $from = CarbonImmutable::now()->subDays(30);

        $events = $club->events()->published()->where('status', '!=', 'cancelled')->visibleTo(null)
            ->where('starts_at', '>=', $from)->where('starts_at', '<=', $from->addMonths(13))
            ->orderBy('starts_at')->get();

        foreach ($events as $event) {
            $start = CarbonImmutable::instance($event->starts_at);
            $end = $event->ends_at ? CarbonImmutable::instance($event->ends_at) : $start->addHours(2);
            $ics->add('event-'.$event->id, $event->title, $start, $end->lt($start) ? $start->addHours(2) : $end, $event->formatted_location ?: null, null, route('public.event', ['clubSlug' => $club->slug, 'eventSlug' => $event->slug]));
        }

        return response($ics->render(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.$club->slug.'.ics"',
            'Cache-Control' => 'public, max-age=900',
        ]);
    }

    /**
     * Handle contact form submission from public website.
     */
    public function submitContactForm(SubmitContactFormRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        // Recipients are never taken on trust from the request. A form may only send to the
        // club's own contact addresses, to addresses on the club's own email domain, or to an
        // active member. Free-mail domains are never treated as "the club's domain".
        $clubEmails = collect([$club->settings['contact_email'] ?? null, $club->email])->filter()->map(fn ($email) => strtolower((string) $email));
        $freeMail = ['gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'hotmail.co.uk', 'live.com', 'yahoo.com', 'yahoo.co.uk', 'icloud.com', 'me.com', 'aol.com', 'btinternet.com', 'sky.com', 'proton.me', 'protonmail.com'];
        $clubDomains = $clubEmails->map(fn ($email) => substr((string) strrchr($email, '@'), 1))->filter()->reject(fn ($domain) => in_array($domain, $freeMail, true))->unique();
        $memberEmails = $club->users()->wherePivot('status', ClubMemberStatus::Active->value)->pluck('users.email')->map(fn ($email) => strtolower((string) $email));

        $isAllowed = fn (string $email) => $clubEmails->contains(strtolower($email))
            || $memberEmails->contains(strtolower($email))
            || $clubDomains->contains(substr((string) strrchr(strtolower($email), '@'), 1));

        $recipientEmail = $club->settings['contact_email'] ?? $club->email;
        if (! empty($validated['recipient_email']) && $isAllowed($validated['recipient_email'])) {
            $recipientEmail = $validated['recipient_email'];
        }

        $ccEmails = [];
        if (! empty($validated['cc_emails'])) {
            foreach (preg_split('/[\s,]+/', $validated['cc_emails']) as $candidate) {
                $candidate = trim($candidate);
                if (filter_var($candidate, FILTER_VALIDATE_EMAIL) && $isAllowed($candidate)) {
                    $ccEmails[] = $candidate;
                }
            }
        }

        $mailable = new ContactFormSubmittedMail(
            club: $club,
            senderName: $validated['name'] ?? 'Anonymous Visitor',
            senderEmail: $validated['email'],
            senderPhone: $validated['phone'] ?? null,
            messageContent: $validated['message'] ?? ''
        );

        $mail = Mail::to($recipientEmail);
        if (! empty($ccEmails)) {
            $mail->cc($ccEmails);
        }
        $mail->send($mailable);

        $msg = $validated['success_message'] ?? 'Thank you! Your message has been sent successfully.';

        return back()->with('success', $msg);
    }
}
