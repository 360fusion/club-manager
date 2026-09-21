<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Page;
use Illuminate\Http\Request;
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
            'custom_domain' => ['nullable', 'string', 'max:255', 'regex:/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,}$/i', Rule::unique('clubs', 'custom_domain')->ignore($club->id)],
            'primary_color' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'social_facebook' => ['nullable', 'string', 'max:500', 'regex:#^https?://#i'],
            'social_instagram' => ['nullable', 'string', 'max:500', 'regex:#^https?://#i'],
            'social_twitter' => ['nullable', 'string', 'max:500', 'regex:#^https?://#i'],
            'header_cta_text' => 'nullable|string|max:255',
            'header_cta_link' => ['nullable', 'string', 'max:500', 'regex:#^(https?://|/|\\#|mailto:)#i'],
            'footer_copyright' => 'nullable|string|max:255',
        ]);

        if (isset($validated['custom_domain']) && $validated['custom_domain'] !== $club->custom_domain) {
            $club->custom_domain = strtolower(trim($validated['custom_domain']));
            $club->domain_status = 'pending';
            $club->domain_verified_at = null;
        }

        $existingSettings = $club->settings ?? [];
        $club->settings = array_merge($existingSettings, $validated);
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
            'website_theme' => 'required|string|in:classic,obsidian,masonic,minimal,vibrant,light_navy,executive_light,masonic_light,warm_light',
        ]);

        $existingSettings = $club->settings ?? [];
        $existingSettings['website_theme'] = $validated['website_theme'];
        $club->settings = $existingSettings;
        $club->save();

        return redirect()->back()->with('success', 'Website theme updated successfully.');
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
        ], $this->getPreviewData($club)));
    }

    /**
     * Store or update a page with block layout.
     */
    public function store(Request $request, string $clubSlug)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'is_published' => 'boolean',
            'is_homepage' => 'boolean',
            'show_in_navigation' => 'boolean',
            'blocks' => 'array',
        ]);

        $isHome = ($validated['slug'] === 'home');
        if ($isHome) {
            Page::where('club_id', $club->id)->where('slug', '!=', 'home')->update(['is_homepage' => false]);
        }

        $isNew = empty($validated['id']);
        $maxSortOrder = (int) (Page::where('club_id', $club->id)->max('sort_order') ?? 0);

        $data = [
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'is_published' => $isHome ? true : ($validated['is_published'] ?? true),
            'is_homepage' => $isHome,
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

        return redirect()->route('admin.pages.edit', ['clubSlug' => $club->slug, 'id' => $page->id])
            ->with('success', 'Page saved successfully.');
    }

    /**
     * Reorder pages for a club. Home page is always kept at top (#1).
     */
    public function reorder(Request $request, string $clubSlug)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'order' => 'required|array',
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
        ];

        return array_merge($defaults, $club->settings ?? []);
    }
}
