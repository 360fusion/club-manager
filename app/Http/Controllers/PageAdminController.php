<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubRedirect;
use App\Models\MasonicHall;
use App\Models\Page;
use App\Models\PageRedirect;
use App\Models\PageRevision;
use App\Services\Geocoder;
use App\Services\PagePublisher;
use App\Services\PublicCalendar;
use App\Support\BlockNormaliser;
use App\Support\ClubDomain;
use App\Support\FooterCopyright;
use App\Support\SiteAnnouncement;
use App\Support\SiteThemes;
use App\Support\SiteTracking;
use App\Support\UploadRules;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PageAdminController extends Controller
{
    /**
     * An uploaded picture's address (a full web address, or a path on this site such as /storage/x.jpg).
     */
    private const IMAGE_URL = 'regex:#^(https?://|/[^/])#i';

    /**
     * @var list<string>
     */
    private const FONT_PAIRINGS = ['theme', 'georgia', 'clean', 'palatino', 'lodge'];

    /**
     * @var list<string>
     */
    private const CORNER_STYLES = ['theme', 'square', 'soft', 'round'];

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
            'share_image_url' => ['nullable', 'string', 'max:2048', self::IMAGE_URL],
            'site_icon_url' => ['nullable', 'string', 'max:2048', self::IMAGE_URL],
            'noindex_site' => 'boolean',
            'analytics_provider' => ['nullable', Rule::in(SiteTracking::PROVIDERS)],
            'analytics_id' => [
                'nullable', 'string', 'max:100', 'required_if:analytics_provider,google,plausible',
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    $provider = $request->input('analytics_provider');

                    if ($provider === 'google' && ! preg_match(SiteTracking::GOOGLE_ID, (string) $value)) {
                        $fail('Enter a Google Analytics measurement ID, which looks like G-ABC123XYZ.');
                    }

                    if ($provider === 'plausible' && ! preg_match(SiteTracking::PLAUSIBLE_DOMAIN, (string) $value)) {
                        $fail('Enter the domain your Plausible site is registered under, such as lodge.org.uk.');
                    }
                },
            ],
            'cookie_banner_enabled' => 'boolean',
            'cookie_banner_text' => 'nullable|string|max:500',
            'cookie_banner_link_label' => 'nullable|string|max:60',
            'cookie_banner_link_url' => ['nullable', 'string', 'max:500', 'regex:#^(https?://|/)#i'],
            'revisions_keep' => ['nullable', 'integer', 'min:'.PagePublisher::MIN_KEEP, 'max:'.PagePublisher::MAX_KEEP],
            'revisions_max_age_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'not_found_page_id' => ['nullable', 'integer', Rule::exists('pages', 'id')->where('club_id', $club->id)->whereNull('deleted_at')],
        ]);

        ClubDomain::apply($club, $validated['custom_domain'] ?? null);

        // The domain lives on the club's own column (ClubDomain owns it); everything else is a website setting.
        $existingSettings = $club->settings ?? [];
        $club->settings = array_merge($existingSettings, collect($validated)->except('custom_domain')->all());
        $club->save();

        return redirect()->back()->with('success', 'Website settings saved successfully.');
    }

    /**
     * The Redirects screen, at its own address so it can be bookmarked and reloaded.
     */
    public function redirects(string $clubSlug): Response
    {
        return $this->builderScreen($clubSlug, 'redirects');
    }

    /**
     * The Manage Pages table, at its own address.
     */
    public function overview(string $clubSlug): Response
    {
        return $this->builderScreen($clubSlug, 'overview', withTrash: true);
    }

    private function builderScreen(string $clubSlug, string $selected, bool $withTrash = false): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $club->ensureDefaultPages();

        $pages = Page::where('club_id', $club->id)->orderBy('sort_order')->orderBy('id')->get();

        return Inertia::render('Admin/PageList', array_merge([
            'club' => $club,
            'pages' => $pages,
            'selectedId' => $selected,
            'websiteSettings' => $this->getWebsiteSettings($club),
        ], $withTrash ? ['trashedPages' => $this->trashedPages($club)] : [], $this->getPreviewData($club)));
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
            'website_theme' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) use ($club) {
                if (! is_string($value) || ! SiteThemes::isValidFor($value, $club)) {
                    $fail('Choose one of the available themes.');
                }
            }],
            'font_pairing' => ['nullable', Rule::in(self::FONT_PAIRINGS)],
            'corner_style' => ['nullable', Rule::in(self::CORNER_STYLES)],
        ]);

        $existingSettings = $club->settings ?? [];
        $existingSettings['website_theme'] = $validated['website_theme'];

        foreach (['font_pairing', 'corner_style'] as $key) {
            if (array_key_exists($key, $validated)) {
                $existingSettings[$key] = $validated[$key] ?: 'theme';
            }
        }
        $club->settings = $existingSettings;
        $club->save();

        return redirect()->back()->with('success', 'Website theme updated successfully.');
    }

    /**
     * Create or update one of the lodge's own named colour schemes. The browser proposes the id of a new scheme;
     * it is only accepted in the expected shape, and a scheme that already has that id is updated in place.
     */
    public function saveColourScheme(Request $request, string $clubSlug)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'id' => ['required', 'string', 'regex:'.SiteThemes::CUSTOM_ID_PATTERN],
            'name' => ['required', 'string', 'max:40'],
            'primary' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $schemes = SiteThemes::customSchemes($club);
        $existing = collect($schemes)->search(fn (array $scheme) => $scheme['id'] === $validated['id']);

        if ($existing === false && count($schemes) >= SiteThemes::MAX_CUSTOM_SCHEMES) {
            throw ValidationException::withMessages(['name' => 'You can keep up to '.SiteThemes::MAX_CUSTOM_SCHEMES.' colour schemes. Delete one to make room.']);
        }

        $name = trim(strip_tags($validated['name']));

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Give the colour scheme a name.']);
        }

        $scheme = ['id' => $validated['id'], 'name' => $name, 'primary' => strtolower($validated['primary']), 'accent' => strtolower($validated['accent'])];

        if ($existing === false) {
            $schemes[] = $scheme;
        } else {
            $schemes[$existing] = $scheme;
        }

        $settings = $club->settings ?? [];
        $settings['custom_color_schemes'] = array_values($schemes);
        $club->settings = $settings;
        $club->save();

        return redirect()->back()->with('success', 'Colour scheme saved.');
    }

    /**
     * Remove one of the lodge's colour schemes, unless the live site is using it.
     */
    public function deleteColourScheme(string $clubSlug, string $schemeId)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        if (str_ends_with((string) ($club->settings['website_theme'] ?? ''), ':'.$schemeId)) {
            throw ValidationException::withMessages(['scheme' => 'The live site is using this colour scheme. Apply a different one first, then delete it.']);
        }

        $settings = $club->settings ?? [];
        $settings['custom_color_schemes'] = array_values(array_filter(SiteThemes::customSchemes($club), fn (array $scheme) => $scheme['id'] !== $schemeId));
        $club->settings = $settings;
        $club->save();

        return redirect()->back()->with('success', 'Colour scheme deleted.');
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

        $urlLikeRule = function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== null && $value !== '' && ! preg_match('#^(https?://|/|\#|mailto:|tel:)#i', $value)) {
                $fail('The :attribute must start with http://, https://, /, # or mailto:.');
            }
        };

        // A link typed without https:// ("example.org") is completed rather than refused, so it opens the other site.
        foreach (['header_cta_link', 'announcement_link_url'] as $field) {
            if (is_string($request->input($field)) && trim($request->input($field)) !== '') {
                $request->merge([$field => BlockNormaliser::link($request->input($field))]);
            }
        }

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
            'footer_show_custom_columns' => 'boolean',
            // Which of the site's menu pages the footer's "Navigate" column lists; null means all of them.
            'footer_nav_page_ids' => ['nullable', 'array', 'max:100'],
            'footer_nav_page_ids.*' => ['integer', Rule::exists('pages', 'id')->where('club_id', $club->id)->whereNull('deleted_at')],
            'footer_about_text' => 'nullable|string|max:300',
            'footer_copyright_holder' => 'nullable|string|max:150',
            'footer_copyright_text' => 'nullable|string|max:200',
            'social_facebook' => ['nullable', 'string', 'max:500', 'regex:#^https?://#i'],
            'social_instagram' => ['nullable', 'string', 'max:500', 'regex:#^https?://#i'],
            'social_twitter' => ['nullable', 'string', 'max:500', 'regex:#^https?://#i'],
            'social_youtube' => ['nullable', 'string', 'max:500', 'regex:#^https?://#i'],
            'social_linkedin' => ['nullable', 'string', 'max:500', 'regex:#^https?://#i'],
            'social_tiktok' => ['nullable', 'string', 'max:500', 'regex:#^https?://#i'],
            'social_whatsapp' => ['nullable', 'string', 'max:500', 'regex:#^https?://#i'],
            'announcement_enabled' => 'boolean',
            'announcement_text' => 'nullable|string|max:200',
            'announcement_link_label' => 'nullable|string|max:60',
            'announcement_link_url' => ['nullable', 'string', 'max:500', 'regex:#^(https?://|/|\#|mailto:|tel:)#i'],
            'announcement_style' => ['nullable', Rule::in(SiteAnnouncement::STYLES)],
            'announcement_dismissible' => 'boolean',
            'announcement_starts_on' => 'nullable|date',
            'announcement_ends_on' => 'nullable|date|after_or_equal:announcement_starts_on',
            'footer_link_columns' => 'nullable|array|max:4',
            'footer_link_columns.*.title' => 'nullable|string|max:100',
            'footer_link_columns.*.links' => 'nullable|array|max:8',
            'footer_link_columns.*.links.*.label' => 'nullable|string|max:100',
            'footer_link_columns.*.links.*.url' => ['nullable', 'string', 'max:500', $urlLikeRule],
        ]);

        // The single line with a typed-in year is replaced by the holder and wording, so the old copy must not linger.
        if (array_key_exists('footer_copyright_holder', $validated) || array_key_exists('footer_copyright_text', $validated)) {
            $validated['footer_copyright'] = null;
        }

        if (array_key_exists('footer_nav_page_ids', $validated) && is_array($validated['footer_nav_page_ids'])) {
            $validated['footer_nav_page_ids'] = array_values(array_unique(array_map('intval', $validated['footer_nav_page_ids'])));
        }

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
     * Turn an address typed into a Map block into candidate map positions (OpenStreetMap Nominatim).
     */
    public function geocode(Request $request, string $clubSlug, Geocoder $geocoder): JsonResponse
    {
        Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate(['q' => 'required|string|max:200']);

        $search = $geocoder->search($validated['q']);

        if ($search === null) {
            return response()->json(['found' => false, 'results' => [], 'message' => 'The map search is not available right now. Click the map to place the pin instead.'], 503);
        }

        return response()->json(['found' => $search['results'] !== []] + $search);
    }

    /**
     * Masonic halls from the directory matching what is being typed into a Map block's address, so a lodge
     * can pick its own hall without any outside lookup.
     */
    public function placeSuggestions(Request $request, string $clubSlug): JsonResponse
    {
        Club::where('slug', $clubSlug)->firstOrFail();

        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 3 || mb_strlen($term) > 100) {
            return response()->json(['places' => []]);
        }

        $like = '%'.addcslashes($term, '\\%_').'%';

        $places = MasonicHall::query()
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('town', 'like', $like)->orWhere('postcode', 'like', $like)->orWhere('address_line_1', 'like', $like))
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (MasonicHall $hall) => [
                'id' => $hall->id,
                'name' => $hall->name,
                'address' => $hall->fullAddress(),
                'label' => $hall->name.($hall->town ? ' — '.$hall->town : '').($hall->postcode ? ', '.$hall->postcode : ''),
            ])
            ->values();

        return response()->json(['places' => $places]);
    }

    /**
     * Store a file for a Downloads block on the private disk: it is only ever served by the site's own
     * download route, which applies the block's members-only setting.
     */
    public function uploadDownload(Request $request, string $clubSlug): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $request->validate([
            'file' => ['required', 'file', 'mimes:'.UploadRules::DOCUMENT_TYPES.','.UploadRules::IMAGE_TYPES, 'max:10240'],
        ]);

        $file = $request->file('file');

        if ($unsafe = UploadRules::assertSafeUpload($file)) {
            return response()->json(['success' => false, 'message' => $unsafe], 422);
        }

        if (! $club->hasStorageFor((int) $file->getSize())) {
            return response()->json(['success' => false, 'message' => 'Storage quota exceeded. Free up space in the file manager first.'], 422);
        }

        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $media = $club->addMediaFromRequest('file')
            ->usingName($baseName)
            ->toMediaCollection('page_downloads', 'local');

        return response()->json([
            'success' => true,
            'media_id' => $media->id,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => (int) $media->size,
            'title' => trim((string) preg_replace('/[_\-]+/', ' ', $baseName)),
            'added_at' => now()->toDateString(),
        ]);
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
            'share_image' => ['nullable', 'string', 'max:2048', self::IMAGE_URL],
            'noindex' => 'boolean',
            'is_published' => 'boolean',
            'publish_at' => 'nullable|date',
            'unpublish_at' => 'nullable|date|after:publish_at',
            'is_homepage' => 'boolean',
            'is_members_only' => 'boolean',
            'show_in_navigation' => 'boolean',
            'show_in_footer' => 'boolean',
            'header_style' => ['nullable', Rule::in(Page::HEADER_STYLES)],
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
     * The fields shared by a full save, a draft and a copy: what the visitor reads on the page.
     *
     * @return array<string, mixed>
     */
    private function validatedContent(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'share_image' => ['nullable', 'string', 'max:2048', self::IMAGE_URL],
            'blocks' => 'array|max:200',
        ]);
    }

    private function pageOf(string $clubSlug, int $id): Page
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        return Page::where('club_id', $club->id)->findOrFail($id);
    }

    /**
     * Keep edits to a live page aside as an unpublished draft. Visitors keep seeing the live version.
     */
    public function saveDraft(Request $request, string $clubSlug, int $id, PagePublisher $publisher): RedirectResponse
    {
        $page = $this->pageOf($clubSlug, $id);

        if (! $page->is_published) {
            throw ValidationException::withMessages(['title' => 'Only a published page can have a draft. Save the page instead.']);
        }

        $publisher->saveDraft($page, $this->validatedContent($request));

        return redirect()->route('admin.pages.edit', ['clubSlug' => $clubSlug, 'id' => $page->id])->with('success', 'Draft saved. Visitors still see the live page.');
    }

    public function discardDraft(string $clubSlug, int $id, PagePublisher $publisher): RedirectResponse
    {
        $publisher->discardDraft($this->pageOf($clubSlug, $id));

        return redirect()->route('admin.pages.edit', ['clubSlug' => $clubSlug, 'id' => $id])->with('success', 'Draft discarded.');
    }

    public function publishDraft(Request $request, string $clubSlug, int $id, PagePublisher $publisher): RedirectResponse
    {
        $page = $this->pageOf($clubSlug, $id);

        abort_unless($page->hasDraft(), 422, 'There is no draft to publish.');

        $publisher->publishDraft($page, $request->user());

        return redirect()->route('admin.pages.edit', ['clubSlug' => $clubSlug, 'id' => $page->id])->with('success', 'Draft published.');
    }

    /**
     * The secret link that shows this page, draft included, to anyone who has it. Asking again with `renew`
     * makes a new link and switches the old one off.
     */
    public function previewLink(Request $request, string $clubSlug, int $id): JsonResponse
    {
        $page = $this->pageOf($clubSlug, $id);

        if (! $page->preview_token || $request->boolean('renew')) {
            $page->forceFill(['preview_token' => Str::random(40)])->save();
        }

        $url = route('public.site', $page->is_homepage ? ['clubSlug' => $clubSlug] : ['clubSlug' => $clubSlug, 'pageSlug' => $page->slug]);

        return response()->json(['url' => $url.'?preview='.$page->preview_token]);
    }

    /**
     * Earlier saves of a page, newest first.
     */
    public function revisions(string $clubSlug, int $id): JsonResponse
    {
        $page = $this->pageOf($clubSlug, $id);

        return response()->json([
            'revisions' => $page->revisions()->with('user:id,name')->latest('id')->limit(PagePublisher::keepFor($page->club))->get()->map(fn (PageRevision $revision) => [
                'id' => $revision->id,
                'created_at' => $revision->created_at?->toIso8601String(),
                'user' => $revision->user?->name,
                'source' => $revision->source,
                'title' => $revision->title,
                'block_count' => count($revision->blocks ?? []),
            ])->values(),
        ]);
    }

    /**
     * One earlier version's content, for loading into the editor (nothing is changed until the admin saves).
     */
    public function revision(string $clubSlug, int $id, int $revisionId): JsonResponse
    {
        $revision = $this->pageOf($clubSlug, $id)->revisions()->findOrFail($revisionId);

        return response()->json(['content' => [
            'title' => $revision->title,
            'meta_title' => $revision->meta_title,
            'meta_description' => $revision->meta_description,
            'share_image' => $revision->share_image,
            'blocks' => $revision->blocks ?? [],
        ]]);
    }

    /**
     * Add a copy of an element to the end of another page of this club.
     */
    public function copyBlock(Request $request, string $clubSlug, int $id, PagePublisher $publisher): JsonResponse
    {
        $target = $this->pageOf($clubSlug, $id);

        // Validated as a whole: a rule on a nested key would make validated() drop the rest of the element.
        $request->validate(['block' => 'required|array']);

        $block = (array) $request->input('block');

        if (! is_string($block['type'] ?? null) || $block['type'] === '' || strlen($block['type']) > 50) {
            throw ValidationException::withMessages(['block' => 'That is not an element that can be copied.']);
        }

        if (strlen((string) json_encode($block)) > 200000) {
            throw ValidationException::withMessages(['block' => 'That element is too large to copy.']);
        }

        // The copy is a new element: it must not share an id with the original, and it is sanitised like any save.
        $block['id'] = 'block-'.now()->timestamp.'-'.Str::lower(Str::random(4));

        $publisher->appendBlock($target, $block, $request->user());

        return response()->json(['ok' => true, 'title' => $target->title, 'to_draft' => $target->hasDraft()]);
    }

    /**
     * Send an address on the club's site (after /site/{club}/) somewhere else, for example from the old website.
     * A page that exists at the address always wins, so a redirect can never hide a live page.
     */
    public function storeRedirect(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'from_path' => ['required', 'string', 'max:200', 'regex:#^[A-Za-z0-9/_\-.~%]+$#'],
            'to_url' => ['required', 'string', 'max:2048', 'regex:#^(https?://|/[^/])#i'],
            'is_permanent' => 'boolean',
        ], ['from_path.regex' => 'Use only letters, numbers and - _ . / in the old address, for example /about-us/history.']);

        $from = ClubRedirect::normalisePath($validated['from_path']);
        $to = trim($validated['to_url']);

        $sitePath = '/site/'.$club->slug.$from;

        if ($from === '/' || ClubRedirect::normalisePath($to) === $sitePath || ClubRedirect::normalisePath($to) === $from) {
            throw ValidationException::withMessages(['from_path' => 'Choose an old address that is different from where it should go.']);
        }

        if (Page::where('club_id', $club->id)->where('is_published', true)->where('slug', ltrim($from, '/'))->exists()) {
            throw ValidationException::withMessages(['from_path' => 'A published page already lives at that address. Unpublish or rename the page first.']);
        }

        if (! ClubRedirect::where('club_id', $club->id)->where('from_path', $from)->exists()
            && ClubRedirect::where('club_id', $club->id)->count() >= ClubRedirect::MAX_PER_CLUB) {
            throw ValidationException::withMessages(['from_path' => 'A site can have up to '.ClubRedirect::MAX_PER_CLUB.' redirects. Remove some you no longer need.']);
        }

        ClubRedirect::updateOrCreate(
            ['club_id' => $club->id, 'from_path' => $from],
            ['to_url' => $to, 'is_permanent' => $validated['is_permanent'] ?? true],
        );

        return redirect()->back()->with('success', 'Redirect saved.');
    }

    public function destroyRedirect(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        ClubRedirect::where('club_id', $club->id)->findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Redirect removed.');
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
            'calendar' => (new PublicCalendar($club, auth()->user()))->forMonth(request('cal')),
            // Offered as "Use the club's address" in the Map block editor.
            'clubAddresses' => array_values(array_filter([
                $club->settings['address'] ?? null ? ['label' => 'the club address', 'address' => (string) $club->settings['address']] : null,
                $club->masonicHall?->fullAddress() ? ['label' => $club->masonicHall->name, 'address' => trim($club->masonicHall->name.', '.$club->masonicHall->fullAddress())] : null,
            ])),
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
            'header_layout' => 'logo_left',
            'header_show_logo' => true,
            'header_show_tagline' => true,
            'header_cta_enabled' => false,
            'header_show_account_links' => true,
            'footer_layout' => 'simple',
            'footer_show_social' => true,
            'footer_show_nav' => false,
            'footer_show_custom_columns' => true,
            'footer_link_columns' => [],
            'revisions_keep' => PagePublisher::DEFAULT_KEEP,
            'revisions_max_age_days' => PagePublisher::DEFAULT_MAX_AGE_DAYS,
        ];

        return array_merge($defaults, $club->settings ?? [], [
            // The domain always comes from the club's own column, never from the settings blob (which may
            // still hold a stale copy from before that was the single source of truth).
            'custom_domain' => $club->custom_domain ?? '',
            'domain_status' => $club->domain_status,
            'domain_verified_at' => $club->domain_verified_at?->format('j M Y, H:i'),
            'domain_instructions' => ClubDomain::instructions(),
            'footer_copyright_holder' => FooterCopyright::parts($club)['holder'],
            'footer_copyright_text' => FooterCopyright::parts($club)['text'],
            'redirects' => ClubRedirect::where('club_id', $club->id)->orderBy('from_path')->get(['id', 'from_path', 'to_url', 'is_permanent']),
        ]);
    }
}
