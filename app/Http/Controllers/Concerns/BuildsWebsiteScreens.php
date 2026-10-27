<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Club;
use App\Models\ClubRedirect;
use App\Models\Page;
use App\Services\PagePublisher;
use App\Services\PublicCalendar;
use App\Support\ClubDomain;
use App\Support\FooterCopyright;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The shared pieces of the website builder screens (page list, settings, themes, header and footer).
 */
trait BuildsWebsiteScreens
{
    protected function builderScreen(string $clubSlug, string $selected, bool $withTrash = false): Response
    {
        $club = $this->findClub($clubSlug);
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
     * @return Collection<int, array<string, mixed>>
     */
    protected function trashedPages(Club $club)
    {
        return Page::onlyTrashed()->where('club_id', $club->id)->orderByDesc('deleted_at')->get()
            ->map(fn (Page $page) => ['id' => $page->id, 'title' => $page->title, 'deleted_at' => $page->deleted_at?->format('j M Y')]);
    }

    /**
     * Helper to load dynamic site preview data for website builder.
     */
    protected function getPreviewData(Club $club): array
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
                'published_at' => ($p->published_at ?? $p->created_at)?->format('j M Y'),
            ]),
            'upcomingEvents' => $club->events->take(3)->values()->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'slug' => $e->slug,
                'location' => $e->location,
                'starts_at' => $e->starts_at->format('j M Y, H:i'),
                'price' => number_format((float) $e->price, 2),
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
        ];
    }

    /**
     * Helper to retrieve website and SEO settings.
     */
    protected function getWebsiteSettings(Club $club): array
    {
        $defaults = [
            'seo_title_suffix' => '| '.$club->name,
            'seo_meta_description' => 'Official website and member portal for '.$club->name,
            'custom_domain' => $club->custom_domain ?? '',
            'primary_color' => '#0369a1',
            'contact_email' => 'admin@'.$club->slug.'.org',
            'phone' => '',
            'address' => '',
            'social_facebook' => '',
            'social_instagram' => '',
            'social_twitter' => '',
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
            'domain_provider' => $club->custom_domain ? ClubDomain::provider($club->custom_domain) : null,
            'footer_copyright_holder' => FooterCopyright::parts($club)['holder'],
            'footer_copyright_text' => FooterCopyright::parts($club)['text'],
            'redirects' => ClubRedirect::where('club_id', $club->id)->orderBy('from_path')->get(['id', 'from_path', 'to_url', 'is_permanent']),
        ]);
    }
}
