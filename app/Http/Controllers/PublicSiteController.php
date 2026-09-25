<?php

namespace App\Http\Controllers;

use App\Mail\ContactFormSubmittedMail;
use App\Models\Club;
use App\Models\ClubRedirect;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageRedirect;
use App\Models\User;
use App\Services\PagePublisher;
use App\Services\PublicCalendar;
use App\Support\ClubAccess;
use App\Support\IcsCalendar;
use App\Support\PageBlocks;
use App\Support\SiteChrome;
use App\Support\SiteSeo;
use App\Support\SiteThemes;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PublicSiteController extends Controller
{
    /**
     * Render a club's public website page.
     */
    public function showPage(string $clubSlug, ?string $pageSlug = null): SymfonyResponse|Response
    {
        $club = Club::where('slug', $clubSlug)
            ->with(['clubType', 'membershipPlans', 'donations.contributions'])
            ->firstOrFail();

        $club->ensureDefaultPages();

        $viewer = Auth::user();

        // A private preview link (?preview=token) shows this one page, draft and all, even before it is live.
        $page = null;
        $preview = false;
        $token = (string) request()->query('preview', '');

        if ($token !== '') {
            $candidate = $pageSlug
                ? Page::where('club_id', $club->id)->where('slug', $pageSlug)->first()
                : Page::where('club_id', $club->id)->where('is_homepage', true)->first();

            if ($candidate && $candidate->preview_token && hash_equals($candidate->preview_token, $token)) {
                $page = $candidate;
                $preview = true;
            }
        }

        // Determine target page (Homepage or specific page slug)
        $query = Page::where('club_id', $club->id)->live();

        if ($preview) {
            // The private link already picked the page, so nothing below applies.
        } elseif ($pageSlug) {
            $page = $query->where('slug', $pageSlug)->first();

            if (! $page) {
                // The page may just have been renamed: an old address keeps working as a redirect rather
                // than 404ing a bookmark or a link from somewhere else.
                $redirect = PageRedirect::where('club_id', $club->id)->where('old_slug', $pageSlug)->first();
                $target = $redirect ? Page::where('club_id', $club->id)->live()->find($redirect->page_id) : null;

                if ($target) {
                    return redirect()->to(route('public.site', $target->is_homepage ? ['clubSlug' => $club->slug] : ['clubSlug' => $club->slug, 'pageSlug' => $target->slug]), 301);
                }

                // An address the lodge pointed somewhere else by hand (from its old website, say).
                $manual = ClubRedirect::where('club_id', $club->id)->where('from_path', ClubRedirect::normalisePath($pageSlug))->first();

                if ($manual) {
                    return redirect()->to($manual->to_url, $manual->is_permanent ? 301 : 302);
                }

                return $this->notFound($club, $viewer);
            }
        } else {
            $page = $query->where('is_homepage', true)->first()
                ?? $query->orderBy('sort_order')->firstOrFail();
        }

        // A page kept for members only is not shown to a visitor who isn't an active member of this club
        // (being logged in as a member elsewhere doesn't count): they are sent to log in.
        if (! $preview && $page->is_members_only && ! ClubAccess::isActiveMember($viewer, $club)) {
            return redirect()->guest(route('login'))->with('error', 'That page is for members only.');
        }

        return $this->renderPage($club, $page, $viewer, 200, $preview);
    }

    /**
     * One news article on the club's public website, drawn inside the site's own header, footer and theme.
     *
     * It is shown as a page that only exists in memory: a heading block, the article's own elements and its
     * attachments, so every element type a news post can hold is drawn the same way it is on an ordinary page.
     */
    public function showPost(string $clubSlug, string $postSlug): SymfonyResponse|Response
    {
        $club = Club::where('slug', $clubSlug)
            ->with(['clubType', 'membershipPlans', 'donations.contributions'])
            ->firstOrFail();

        $viewer = Auth::user();
        $newestFirst = 'COALESCE(posts.published_at, posts.created_at) DESC';

        $post = $club->posts()->with('author')->published()->where('slug', $postSlug)->orderByRaw($newestFirst)->first();

        abort_unless($post, 404);

        if (! $club->posts()->published()->visibleTo($viewer)->whereKey($post->id)->exists()) {
            // A members-only article: a visitor is sent to log in, a signed-in non-member just gets the not-found page.
            abort_if($viewer, 404);

            return redirect()->guest(route('login'))->with('error', 'That article is for members only.');
        }

        $newsPage = Page::where('club_id', $club->id)->live()->where('is_members_only', false)->orderBy('sort_order')->get()
            ->first(fn (Page $page) => collect($page->blocks ?? [])->contains(fn ($block) => in_array($block['type'] ?? null, ['news_feed', 'news_list'], true)));

        $blocks = [[
            'id' => 'post-header',
            'type' => 'post_header',
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'cover_image_url' => $post->cover_image_url ?: ($post->getFirstMediaUrl('cover') ?: null),
            'author_name' => $post->author?->name ?? 'Club Admin',
            'published_at' => ($post->published_at ?? $post->created_at)?->format('j F Y'),
            'back_url' => route('public.site', array_filter(['clubSlug' => $club->slug, 'pageSlug' => $newsPage && ! $newsPage->is_homepage ? $newsPage->slug : null])),
            'back_label' => $newsPage?->title ?: 'News',
        ]];

        $body = $post->blocks ?? [];

        if ($body === [] && trim(strip_tags((string) $post->content)) !== '') {
            $body = [['id' => 'post-body', 'type' => 'text', 'content' => $post->content]];
        }

        array_push($blocks, ...$body);

        $attachments = collect($post->attachments ?? [])
            ->filter(fn ($file) => is_array($file) && ! empty($file['url']))
            ->map(fn (array $file) => ['name' => (string) ($file['name'] ?? 'Download'), 'url' => (string) $file['url'], 'mime_type' => (string) ($file['mime_type'] ?? ''), 'size' => (string) ($file['size'] ?? '')])
            ->values()->all();

        if ($attachments !== []) {
            $blocks[] = ['id' => 'post-attachments', 'type' => 'post_attachments', 'items' => $attachments];
        }

        $page = new Page([
            'club_id' => $club->id,
            'title' => $post->title,
            'slug' => 'news/'.$post->slug,
            'meta_description' => $post->excerpt ?: null,
            'share_image' => $post->cover_image_url ?: null,
            'is_published' => true,
            'is_members_only' => false,
            'noindex' => $post->visibility->value !== 'public',
            'blocks' => $blocks,
        ]);

        $latest = $club->posts()->published()->visibleTo($viewer)->whereKeyNot($post->id)->orderByRaw($newestFirst)->take(5)->get()
            ->map(fn ($other) => [
                'id' => $other->id,
                'title' => $other->title,
                'url' => route('public.site.post', ['clubSlug' => $club->slug, 'postSlug' => $other->slug]),
                'published_at' => ($other->published_at ?? $other->created_at)?->format('j F Y'),
                'cover_image_url' => $other->cover_image_url ?: ($other->getFirstMediaUrl('cover') ?: null),
            ])->all();

        return $this->renderPage($club, $page, $viewer, extra: [
            'articleSidebar' => ['heading' => 'Latest news', 'items' => $latest, 'all_url' => $blocks[0]['back_url'], 'all_label' => 'All news'],
        ]);
    }

    /**
     * The page the club chose to show for an address that does not exist (with a 404 status, so search
     * engines still treat it as missing), or the ordinary not-found error if it has not chosen one.
     */
    private function notFound(Club $club, ?User $viewer): SymfonyResponse|Response
    {
        $id = $club->settings['not_found_page_id'] ?? null;

        $page = $id
            ? Page::where('club_id', $club->id)->live()->where('is_members_only', false)->find($id)
            : null;

        abort_unless($page, 404);

        return $this->renderPage($club, $page, $viewer, 404);
    }

    /**
     * Everything the public site needs to draw one page.
     */
    private function renderPage(Club $club, Page $page, ?User $viewer, int $status = 200, bool $preview = false, array $extra = []): SymfonyResponse|Response
    {
        if ($preview) {
            // What the page will look like once published. Only held in memory, never saved.
            $content = app(PagePublisher::class)->effectiveContent($page);
            $page->title = $content['title'];
            $page->meta_title = $content['meta_title'];
            $page->meta_description = $content['meta_description'];
            $page->share_image = $content['share_image'];
            $page->blocks = $content['blocks'];
        }

        $rawPreviewTheme = request('preview_theme');
        $previewTheme = is_string($rawPreviewTheme) && SiteThemes::isValidFor($rawPreviewTheme, $club) ? $rawPreviewTheme : null;

        $response = Inertia::render('Public/Site', [
            'previewTheme' => $previewTheme,
            ...$extra,
            ...SiteChrome::forClub($club),
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'blocks' => PageBlocks::forViewer($page->blocks ?? [], $club, $viewer),
                'is_homepage' => $page->is_homepage,
                'is_members_only' => $page->is_members_only,
                'header_style' => $page->header_style ?: 'full',
            ],
            'preview' => $preview ? ['has_draft' => $page->hasDraft(), 'is_live' => $page->isLive()] : null,
            // Only worked out for a page that has a calendar block, and lazily so a month change (a partial reload) is cheap.
            'calendar' => collect($page->blocks ?? [])->contains('type', 'calendar')
                ? fn () => (new PublicCalendar($club, $viewer))->forMonth(request('cal'))
                : null,
            'latestPosts' => $club->posts()->with('author')->published()->visibleTo($viewer)->take(24)->get()->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'excerpt' => $p->excerpt,
                'content' => $p->content,
                'cover_image_url' => $p->cover_image_url ?: ($p->getFirstMediaUrl('cover') ?: null),
                'author_name' => $p->author?->name ?? 'Club Admin',
                'published_at' => ($p->published_at ?? $p->created_at)?->format('M d, Y'),
            ]),
            'upcomingEvents' => $club->events()->published()->visibleTo($viewer)->limit(3)->get()->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'slug' => $e->slug,
                'location' => $e->location,
                'starts_at' => $e->starts_at?->format('M d, Y @ H:i'),
                'is_recurring' => $e->is_recurring,
                'requires_payment' => $e->requires_payment,
                'price' => number_format($e->price, 2),
                'has_dining' => $e->has_dining,
                'dining_price' => number_format($e->dining_price, 2),
            ]),
            'membershipPlans' => $club->membershipPlans->map(fn ($mp) => [
                'id' => $mp->id,
                'name' => $mp->name,
                'description' => $mp->description,
                'price' => number_format($mp->price, 2),
                'billing_period' => $mp->billing_period,
            ]),
            'donations' => $club->donations->map(fn ($d) => [
                'id' => $d->id,
                'campaign_name' => $d->campaign_name,
                'target_amount' => number_format($d->target_amount, 2),
                'current_amount' => number_format($d->current_amount, 2),
                'percentage' => $d->percentage,
                'description' => $d->description,
                'status' => $d->status,
                'contributions_count' => $d->contributions->count(),
            ]),
        ]);

        $seoEvents = collect($page->blocks ?? [])->contains(fn ($block) => in_array($block['type'] ?? null, ['calendar', 'events_calendar'], true))
            ? $club->events()->published()->where('status', '!=', 'cancelled')->visibleTo(null)->where('starts_at', '>=', now())->orderBy('starts_at')->limit(10)->get()
            : collect();

        $response->withViewData(['seo' => SiteSeo::forPage($club, $page, request(), $seoEvents, $preview)]);

        if ($status === 200 && ! $preview) {
            return $response;
        }

        $symfony = $response->toResponse(request())->setStatusCode($status);

        if ($preview) {
            // A private link must never be kept by a browser or shared cache.
            $symfony->headers->set('Cache-Control', 'no-store, private');
        }

        return $symfony;
    }

    /**
     * A file uploaded to a Downloads block. It lives on the private disk, so this is the only way to reach it:
     * it must be listed in a downloads block on a published page of this club, and a members-only block (or a
     * members-only page) is only served to an active member.
     */
    public function file(string $clubSlug, int $mediaId): BinaryFileResponse|RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

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
    public function calendarFeed(string $clubSlug): \Illuminate\Http\Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

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
    public function submitContactForm(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'message' => 'nullable|string|max:5000',
            'recipient_email' => 'nullable|email|max:255',
            'cc_emails' => 'nullable|string|max:1000',
            'success_message' => 'nullable|string|max:1000',
        ]);

        // Recipients are never taken on trust from the request. A form may only send to the
        // club's own contact addresses, to addresses on the club's own email domain, or to an
        // active member. Free-mail domains are never treated as "the club's domain".
        $clubEmails = collect([$club->settings['contact_email'] ?? null, $club->email])->filter()->map(fn ($email) => strtolower((string) $email));
        $freeMail = ['gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'hotmail.co.uk', 'live.com', 'yahoo.com', 'yahoo.co.uk', 'icloud.com', 'me.com', 'aol.com', 'btinternet.com', 'sky.com', 'proton.me', 'protonmail.com'];
        $clubDomains = $clubEmails->map(fn ($email) => substr((string) strrchr($email, '@'), 1))->filter()->reject(fn ($domain) => in_array($domain, $freeMail, true))->unique();
        $memberEmails = $club->users()->wherePivot('status', 'active')->pluck('users.email')->map(fn ($email) => strtolower((string) $email));

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
