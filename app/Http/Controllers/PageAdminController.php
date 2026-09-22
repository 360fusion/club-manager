<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Page;
use App\Models\PageRedirect;
use App\Support\ClubDomain;
use App\Support\SiteThemes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PageAdminController extends Controller
{
    /**
     * Display a listing of club pages in admin builder.
     */
    public function index(string $clubSlug, Request $request): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $club->ensureDefaultPages();

        $pages = Page::where('club_id', $club->id)->orderBy('sort_order')->orderBy('id')->get();
        $selectedId = $request->query('page')
            ? (int) $request->query('page')
            : ($pages->firstWhere('is_homepage', true)?->id ?? $pages->first()?->id);

        return Inertia::render('Admin/PageList', array_merge([
            'club' => $club,
            'pages' => $pages,
            'selectedId' => $selectedId,
            'websiteSettings' => $this->getWebsiteSettings($club),
            'trashedPages' => $this->trashedPages($club),
        ], $this->getPreviewData($club)));
    }

    /**
     * Display global website & SEO settings editor inside Website Builder.
     */
    public function settings(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $club->ensureDefaultPages();

        $pages = Page::where('club_id', $club->id)->orderBy('sort_order')->orderBy('id')->get();

        return Inertia::render('Admin/PageList', array_merge([
            'club' => $club,
            'pages' => $pages,
            'selectedId' => 'settings',
            'websiteSettings' => $this->getWebsiteSettings($club),
        ], $this->getPreviewData($club)));
    }

    /**
     * Update global website & SEO settings.
     */
    public function updateSettings(Request $request, string $clubSlug)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'seo_title_suffix' => 'nullable|string|max:255',
            'seo_meta_description' => 'nullable|string|max:1000',
            'custom_domain' => ClubDomain::rule($club),
            'primary_color' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
        ]);

        ClubDomain::apply($club, $validated['custom_domain'] ?? null);

        // The domain lives on the club's own column (ClubDomain owns it); everything else is a website setting.
        $existingSettings = $club->settings ?? [];
        $club->settings = array_merge($existingSettings, collect($validated)->except('custom_domain')->all());
        $club->save();

        return redirect()->back()->with('success', 'Website settings saved successfully.');
    }

    /**
     * Display theme selection gallery inside Website Builder.
     */
    public function themes(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $club->ensureDefaultPages();

        $pages = Page::where('club_id', $club->id)->orderBy('sort_order')->orderBy('id')->get();

        return Inertia::render('Admin/PageList', array_merge([
            'club' => $club,
            'pages' => $pages,
            'selectedId' => 'themes',
            'websiteSettings' => $this->getWebsiteSettings($club),
        ], $this->getPreviewData($club)));
    }

    /**
     * Update active website theme layout.
     */
    public function updateTheme(Request $request, string $clubSlug)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'website_theme' => ['required', 'string', Rule::in(SiteThemes::keys())],
        ]);

        $existingSettings = $club->settings ?? [];
        $existingSettings['website_theme'] = $validated['website_theme'];
        $club->settings = $existingSettings;
        $club->save();

        return redirect()->back()->with('success', 'Website theme updated successfully.');
    }

    /**
     * Display header & footer layout settings inside Website Builder.
     */
    public function headerFooter(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $club->ensureDefaultPages();

        $pages = Page::where('club_id', $club->id)->orderBy('sort_order')->orderBy('id')->get();

        return Inertia::render('Admin/PageList', array_merge([
            'club' => $club,
            'pages' => $pages,
            'selectedId' => 'header_footer',
            'websiteSettings' => $this->getWebsiteSettings($club),
        ], $this->getPreviewData($club)));
    }

    /**
     * Update header & footer layout, visibility and content settings.
     */
    public function updateHeaderFooter(Request $request, string $clubSlug)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $urlLikeRule = function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value !== null && $value !== '' && ! preg_match('#^(https?://|/|\#|mailto:|tel:)#i', $value)) {
                $fail('The :attribute must start with http://, https://, /, # or mailto:.');
            }
        };

        $validated = $request->validate([
            'header_layout' => 'required|string|in:logo_left,logo_center',
            'header_show_logo' => 'boolean',
            'header_show_tagline' => 'boolean',
            'header_cta_enabled' => 'boolean',
            'header_cta_text' => 'nullable|string|max:255',
            'header_cta_link' => ['nullable', 'string', 'max:500', 'regex:#^(https?://|/|\\#|mailto:)#i'],
            'header_show_account_links' => 'boolean',
            'footer_layout' => 'required|string|in:simple,columns',
            'footer_show_social' => 'boolean',
            'footer_show_nav' => 'boolean',
            'footer_copyright' => 'nullable|string|max:255',
            'social_facebook' => ['nullable', 'string', 'max:500', 'regex:#^https?://#i'],
            'social_instagram' => ['nullable', 'string', 'max:500', 'regex:#^https?://#i'],
            'social_twitter' => ['nullable', 'string', 'max:500', 'regex:#^https?://#i'],
            'footer_link_columns' => 'nullable|array|max:4',
            'footer_link_columns.*.title' => 'nullable|string|max:100',
            'footer_link_columns.*.links' => 'nullable|array|max:8',
            'footer_link_columns.*.links.*.label' => 'nullable|string|max:100',
            'footer_link_columns.*.links.*.url' => ['nullable', 'string', 'max:500', $urlLikeRule],
        ]);

        // Drop any column/link the admin left half-filled in (no title, or no label+url pair) rather than
        // failing the whole save over an empty row left over from clicking "Add".
        $validated['footer_link_columns'] = collect($validated['footer_link_columns'] ?? [])
            ->map(fn (array $column) => [
                'title' => $column['title'] ?? '',
                'links' => collect($column['links'] ?? [])
                    ->filter(fn (array $link) => ! empty($link['label']) && ! empty($link['url']))
                    ->values()->all(),
            ])
            ->filter(fn (array $column) => $column['title'] !== '' && count($column['links']) > 0)
            ->values()->all();

        $existingSettings = $club->settings ?? [];
        $club->settings = array_merge($existingSettings, $validated);
        $club->save();

        return redirect()->back()->with('success', 'Header & footer settings saved successfully.');
    }

    /**
     * Show page builder editor for creating or editing a page.
     */
    public function edit(string $clubSlug, ?int $id = null): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $club->ensureDefaultPages();

        $pages = Page::where('club_id', $club->id)->orderBy('sort_order')->orderBy('id')->get();

        return Inertia::render('Admin/PageList', array_merge([
            'club' => $club,
            'pages' => $pages,
            'selectedId' => $id,
            'websiteSettings' => $this->getWebsiteSettings($club),
            'trashedPages' => $this->trashedPages($club),
        ], $this->getPreviewData($club)));
    }

    /**
     * Store or update a page with block layout. Renaming a page's slug keeps the old address working as a
     * redirect, so a bookmark or a link from elsewhere never suddenly 404s.
     */
    public function store(Request $request, string $clubSlug)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|alpha_dash',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_published' => 'boolean',
            'is_homepage' => 'boolean',
            'is_members_only' => 'boolean',
            'show_in_navigation' => 'boolean',
            'blocks' => 'array|max:200',
        ]);

        $isHome = ($validated['slug'] === 'home');
        if ($isHome) {
            Page::where('club_id', $club->id)->where('slug', '!=', 'home')->update(['is_homepage' => false]);
        }

        $isNew = empty($validated['id']);
        $existing = $isNew ? null : Page::where('club_id', $club->id)->find($validated['id']);
        $maxSortOrder = (int) (Page::where('club_id', $club->id)->max('sort_order') ?? 0);

        $data = [
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
            'is_published' => $isHome ? true : ($validated['is_published'] ?? true),
            'is_homepage' => $isHome,
            'is_members_only' => $isHome ? false : ($validated['is_members_only'] ?? false),
            'show_in_navigation' => $validated['show_in_navigation'] ?? true,
            'blocks' => $validated['blocks'] ?? [],
        ];

        if ($isNew) {
            $data['sort_order'] = $maxSortOrder + 1;
        }

        $page = Page::updateOrCreate(
            ['id' => $validated['id'] ?? null, 'club_id' => $club->id],
            $data
        );

        if ($existing && $existing->slug !== $page->slug) {
            $this->keepOldSlugWorking($club, $page, $existing->slug);
        }

        return redirect()->route('admin.pages.edit', ['clubSlug' => $club->slug, 'id' => $page->id])
            ->with('success', 'Page saved successfully.');
    }

    /**
     * A copy of a page as a new draft, for reusing a layout without starting from scratch.
     */
    public function duplicate(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $page = Page::where('club_id', $club->id)->findOrFail($id);

        $slug = Str::slug($page->title.'-copy');
        $unique = $slug;
        $n = 2;

        while (Page::where('club_id', $club->id)->where('slug', $unique)->exists()) {
            $unique = $slug.'-'.$n++;
        }

        $copy = Page::create([
            'club_id' => $club->id,
            'title' => 'Copy of '.$page->title,
            'slug' => $unique,
            'meta_title' => $page->meta_title,
            'meta_description' => $page->meta_description,
            'blocks' => $page->blocks,
            'is_published' => false,
            'is_homepage' => false,
            'is_members_only' => $page->is_members_only,
            'show_in_navigation' => false,
            'sort_order' => (int) (Page::where('club_id', $club->id)->max('sort_order') ?? 0) + 1,
        ]);

        return redirect()->route('admin.pages.edit', ['clubSlug' => $club->slug, 'id' => $copy->id])
            ->with('success', 'Page duplicated as a new draft.');
    }

    /**
     * Ask Stripe... no, ask DNS: check whether the saved custom domain now points here.
     */
    public function verifyDomain(string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        if (! $club->custom_domain) {
            return redirect()->back()->with('error', 'Save a custom domain first.');
        }

        $verified = ClubDomain::verify($club);

        return redirect()->back()->with($verified ? 'success' : 'error', $verified
            ? 'The domain is verified and live.'
            : "The DNS record wasn't found yet. It can take a while to update \u{2014} try again shortly.");
    }

    /**
     * Restore a page from the trash.
     */
    public function restore(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $page = Page::onlyTrashed()->where('club_id', $club->id)->findOrFail($id);
        $page->restore();

        return redirect()->back()->with('success', "'{$page->title}' restored.");
    }

    /**
     * Remove a trashed page for good.
     */
    public function forceDelete(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $page = Page::onlyTrashed()->where('club_id', $club->id)->findOrFail($id);
        $page->forceDelete();

        return redirect()->back()->with('success', 'Page removed for good.');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function trashedPages(Club $club)
    {
        return Page::onlyTrashed()->where('club_id', $club->id)->orderByDesc('deleted_at')->get()
            ->map(fn (Page $page) => ['id' => $page->id, 'title' => $page->title, 'deleted_at' => $page->deleted_at?->format('j M Y')]);
    }

    /**
     * Points the page's previous slug at it as a redirect. Every slug this page has ever had keeps working,
     * because a redirect always resolves to wherever the page lives right now, however many times it is
     * renamed again after this. Clears any redirect that already pointed at the slug the page is taking
     * over, so a slug can always be reclaimed by a real page instead of staying shadowed by an old redirect.
     */
    private function keepOldSlugWorking(Club $club, Page $page, string $oldSlug): void
    {
        PageRedirect::where('club_id', $club->id)->where('old_slug', $page->slug)->delete();

        PageRedirect::updateOrCreate(['club_id' => $club->id, 'old_slug' => $oldSlug], ['page_id' => $page->id]);
    }

    /**
     * Reorder pages for a club. Home page is always kept at top (#1).
     */
    public function reorder(Request $request, string $clubSlug)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'order' => 'required|array|max:500',
            'order.*' => 'integer',
        ]);

        $orderIds = $validated['order'];
        $homepage = Page::where('club_id', $club->id)
            ->where(function ($q) {
                $q->where('is_homepage', true)->orWhere('slug', 'home');
            })
            ->first();

        $orderIndex = 1;
        if ($homepage) {
            $homepage->update(['sort_order' => $orderIndex++]);
        }

        foreach ($orderIds as $pageId) {
            if ($homepage && $pageId == $homepage->id) {
                continue;
            }
            Page::where('club_id', $club->id)
                ->where('id', $pageId)
                ->update(['sort_order' => $orderIndex++]);
        }

        return redirect()->back()->with('success', 'Page order updated.');
    }

    /**
     * Toggle publish status of a page.
     */
    public function togglePublish(string $clubSlug, int $id)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $page = Page::where('club_id', $club->id)->findOrFail($id);

        if ($page->is_homepage || $page->slug === 'home') {
            return redirect()->back()->with('error', 'Homepage must remain published.');
        }

        $page->update(['is_published' => ! $page->is_published]);

        return redirect()->back()->with('success', 'Page status updated.');
    }

    /**
     * Delete a page (only custom pages can be deleted; original default pages cannot).
     */
    public function destroy(string $clubSlug, int $id)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $page = Page::where('club_id', $club->id)->findOrFail($id);

        $defaultSlugs = ['home', 'about', 'join-us', 'news', 'contact'];

        if ($page->is_homepage || in_array($page->slug, $defaultSlugs, true)) {
            return redirect()->back()->with('error', 'Original default pages cannot be deleted.');
        }

        $page->delete();

        return redirect()->route('admin.pages.index', ['clubSlug' => $club->slug])
            ->with('success', 'Page deleted successfully.');
    }

    /**
     * Helper to load dynamic site preview data for website builder.
     */
    private function getPreviewData(Club $club): array
    {
        return [
            'latestPosts' => $club->posts()->published()->take(24)->get()->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'excerpt' => $p->excerpt,
                'content' => $p->content,
                'cover_image_url' => $p->cover_image_url ?: ($p->getFirstMediaUrl('cover') ?: null),
                'author_name' => $p->author?->name ?? 'Club Admin',
                'published_at' => ($p->published_at ?? $p->created_at)?->format('M d, Y'),
            ]),
            'upcomingEvents' => $club->events->take(3)->values()->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'slug' => $e->slug,
                'location' => $e->location,
                'starts_at' => $e->starts_at?->format('M d, Y @ H:i'),
                'price' => number_format($e->price, 2),
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
        ];
    }

    /**
     * Helper to retrieve website and SEO settings.
     */
    private function getWebsiteSettings(Club $club): array
    {
        $defaults = [
            'seo_title_suffix' => '| '.$club->name,
            'seo_meta_description' => 'Official website and member portal for '.$club->name,
            'custom_domain' => $club->custom_domain ?? '',
            'primary_color' => '#0369a1',
            'contact_email' => 'admin@'.$club->slug.'.org',
            'phone' => '+44 20 7946 0912',
            'address' => '100 Boathouse Way, Oxford, UK',
            'social_facebook' => 'https://facebook.com',
            'social_instagram' => 'https://instagram.com',
            'social_twitter' => 'https://x.com',
            'header_cta_text' => 'Join Our Club',
            'header_cta_link' => '/site/'.$club->slug.'/join-us',
            'footer_copyright' => '© '.date('Y').' '.$club->name.'. All rights reserved.',
            'header_layout' => 'logo_left',
            'header_show_logo' => true,
            'header_show_tagline' => true,
            'header_cta_enabled' => false,
            'header_show_account_links' => true,
            'footer_layout' => 'simple',
            'footer_show_social' => true,
            'footer_show_nav' => false,
            'footer_link_columns' => [],
        ];

        return array_merge($defaults, $club->settings ?? [], [
            // The domain always comes from the club's own column, never from the settings blob (which may
            // still hold a stale copy from before that was the single source of truth).
            'custom_domain' => $club->custom_domain ?? '',
            'domain_status' => $club->domain_status,
            'domain_verified_at' => $club->domain_verified_at?->format('j M Y, H:i'),
            'domain_instructions' => ClubDomain::instructions(),
        ]);
    }
}
