<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsWebsiteScreens;
use App\Http\Requests\PageAdmin\ReorderRequest;
use App\Http\Requests\PageAdmin\StorePageRequest;
use App\Models\Club;
use App\Models\Page;
use App\Models\PageRedirect;
use App\Services\PagePublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PageAdminController extends Controller
{
    use BuildsWebsiteScreens;

    /**
     * An uploaded picture's address (a full web address, or a path on this site such as /storage/x.jpg).
     */

    /**
     * Display a listing of club pages in admin builder.
     */
    public function index(string $clubSlug, Request $request): Response
    {
        $club = $this->findClub($clubSlug);
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
     * Show page builder editor for creating or editing a page.
     */
    public function edit(string $clubSlug, ?int $id = null): Response
    {
        $club = $this->findClub($clubSlug);
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
    public function store(StorePageRequest $request, string $clubSlug)
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

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
            'share_image' => $validated['share_image'] ?? null,
            'noindex' => $isHome ? false : ($validated['noindex'] ?? false),
            'is_published' => $isHome ? true : ($validated['is_published'] ?? true),
            'is_homepage' => $isHome,
            'is_members_only' => $isHome ? false : ($validated['is_members_only'] ?? false),
            'show_in_navigation' => $validated['show_in_navigation'] ?? true,
            'show_in_footer' => $validated['show_in_footer'] ?? false,
            'header_style' => $validated['header_style'] ?? 'full',
            // The homepage is always live, so it never carries dates.
            'publish_at' => $isHome ? null : ($validated['publish_at'] ?? null),
            'unpublish_at' => $isHome ? null : ($validated['unpublish_at'] ?? null),
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

        // A full save makes this the live version: any draft is now out of date, and the version is remembered.
        app(PagePublisher::class)->applyLive($page, app(PagePublisher::class)->liveContent($page), $request->user(), 'save');

        return redirect()->route('admin.pages.edit', ['clubSlug' => $club->slug, 'id' => $page->id])
            ->with('success', 'Page saved successfully.');
    }

    /**
     * A copy of a page as a new draft, for reusing a layout without starting from scratch.
     */
    public function duplicate(string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
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
            'share_image' => $page->share_image,
            'noindex' => $page->noindex,
            'blocks' => $page->blocks,
            'is_published' => false,
            'is_homepage' => false,
            'is_members_only' => $page->is_members_only,
            'header_style' => $page->header_style,
            'show_in_navigation' => false,
            'sort_order' => (int) (Page::where('club_id', $club->id)->max('sort_order') ?? 0) + 1,
        ]);

        return redirect()->route('admin.pages.edit', ['clubSlug' => $club->slug, 'id' => $copy->id])
            ->with('success', 'Page duplicated as a new draft.');
    }

    /**
     * Restore a page from the trash.
     */
    public function restore(string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $page = Page::onlyTrashed()->where('club_id', $club->id)->findOrFail($id);
        $page->restore();

        return redirect()->back()->with('success', "'{$page->title}' restored.");
    }

    /**
     * Remove a trashed page for good.
     */
    public function forceDelete(string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $page = Page::onlyTrashed()->where('club_id', $club->id)->findOrFail($id);
        $page->forceDelete();

        return redirect()->back()->with('success', 'Page removed for good.');
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
    public function reorder(ReorderRequest $request, string $clubSlug)
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

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
        $club = $this->findClub($clubSlug);
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
        $club = $this->findClub($clubSlug);
        $page = Page::where('club_id', $club->id)->findOrFail($id);

        $defaultSlugs = ['home', 'about', 'join-us', 'news', 'contact'];

        if ($page->is_homepage || in_array($page->slug, $defaultSlugs, true)) {
            return redirect()->back()->with('error', 'Original default pages cannot be deleted.');
        }

        $page->delete();

        return redirect()->route('admin.pages.index', ['clubSlug' => $club->slug])
            ->with('success', 'Page deleted successfully.');
    }
}
