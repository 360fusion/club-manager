<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubRedirect;
use App\Models\Page;
use App\Models\PageRedirect;
use App\Models\User;
use App\Services\PagePublisher;
use App\Services\PublicCalendar;
use App\Support\ClubAccess;
use App\Support\PageBlocks;
use App\Support\SiteChrome;
use App\Support\SiteSeo;
use App\Support\SiteThemes;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
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
            ? Page::where('club_id', $club->id)->live()->where('is_members_only', false)->find((int) $id)
            : null;

        if (! $page) {
            abort(404);
        }

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
                'published_at' => ($p->published_at ?? $p->created_at)?->format('j M Y'),
            ]),
            'upcomingEvents' => $club->events()->published()->visibleTo($viewer)->limit(3)->get()->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'slug' => $e->slug,
                'location' => $e->location,
                'starts_at' => $e->starts_at->format('j M Y, H:i'),
                'is_recurring' => $e->is_recurring,
                'requires_payment' => $e->requires_payment,
                'price' => number_format((float) $e->price, 2),
                'has_dining' => $e->has_dining,
                'dining_price' => number_format((float) $e->dining_price, 2),
            ]),
            'membershipPlans' => $club->membershipPlans->map(fn ($mp) => [
                'id' => $mp->id,
                'name' => $mp->name,
                'description' => $mp->description,
                'price' => number_format((float) $mp->price, 2),
                'billing_period' => $mp->billing_period,
            ]),
            'donations' => $club->donations->map(fn ($d) => [
                'id' => $d->id,
                'campaign_name' => $d->campaign_name,
                'target_amount' => number_format((float) $d->target_amount, 2),
                'current_amount' => number_format((float) $d->current_amount, 2),
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
}
