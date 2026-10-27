<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsWebsiteScreens;
use App\Http\Requests\PageAdmin\UpdateSiteSettingsRequest;
use App\Models\Page;
use App\Support\BlockNormaliser;
use App\Support\ClubDomain;
use App\Support\SiteAnnouncement;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Website builder settings screens: global settings, redirects, themes, header and footer, domain check. Split out of PageAdminController; the URLs, route names and permissions are unchanged.
 */
class PageSiteSettingsController extends Controller
{
    use BuildsWebsiteScreens;

    /**
     * An uploaded picture's address (a full web address, or a path on this site such as /storage/x.jpg).
     */

    /**
     * Display global website & SEO settings editor inside Website Builder.
     */
    public function settings(string $clubSlug): Response
    {
        $club = $this->findClub($clubSlug);
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
    public function updateSettings(UpdateSiteSettingsRequest $request, string $clubSlug)
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

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

    /**
     * Display theme selection gallery inside Website Builder.
     */
    public function themes(string $clubSlug): Response
    {
        $club = $this->findClub($clubSlug);
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
     * Display header & footer layout settings inside Website Builder.
     */
    public function headerFooter(string $clubSlug): Response
    {
        $club = $this->findClub($clubSlug);
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
        $club = $this->findClub($clubSlug);

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
     * Ask Stripe... no, ask DNS: check whether the saved custom domain now points here.
     */
    public function verifyDomain(string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        if (! $club->custom_domain) {
            return redirect()->back()->with('error', 'Save a custom domain first.');
        }

        $verified = ClubDomain::verify($club);

        return redirect()->back()->with($verified ? 'success' : 'error', $verified
            ? 'The domain is verified and live.'
            : "The DNS record wasn't found yet. It can take a while to update \u{2014} try again shortly.");
    }
}
