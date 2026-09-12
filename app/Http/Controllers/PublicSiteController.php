<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Page;
use Inertia\Inertia;
use Inertia\Response;

class PublicSiteController extends Controller
{
    /**
     * Render a club's public website page.
     */
    public function showPage(string $clubSlug, ?string $pageSlug = null): Response
    {
        $club = Club::where('slug', $clubSlug)
            ->with(['clubType', 'membershipPlans', 'posts.author', 'events.menuItems', 'events.attendees', 'donations.contributions'])
            ->firstOrFail();

        // Determine target page (Homepage or specific page slug)
        $query = Page::where('club_id', $club->id)->where('is_published', true);

        if ($pageSlug) {
            $page = $query->where('slug', $pageSlug)->firstOrFail();
        } else {
            $page = $query->where('is_homepage', true)->first() 
                ?? $query->orderBy('sort_order')->firstOrFail();
        }

        // Navigation links (All published pages marked show_in_navigation)
        $navigationPages = Page::where('club_id', $club->id)
            ->where('is_published', true)
            ->where('show_in_navigation', true)
            ->orderBy('sort_order')
            ->get(['id', 'title', 'slug', 'is_homepage']);

        return Inertia::render('Public/Site', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
                'type_name' => $club->clubType->name,
                'tagline' => $club->settings['tagline'] ?? '',
                'primary_color' => $club->settings['primary_color'] ?? '#0369a1',
            ],
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'blocks' => $page->blocks ?? [],
                'is_homepage' => $page->is_homepage,
                'is_members_only' => $page->is_members_only,
            ],
            'navigation' => $navigationPages,
            'latestPosts' => $club->posts->where('status', 'published')->take(3)->values()->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'excerpt' => $p->excerpt,
                'content' => $p->content,
                'author_name' => $p->author->name,
                'published_at' => $p->published_at?->format('M d, Y'),
            ]),
            'upcomingEvents' => $club->events->take(3)->values()->map(fn ($e) => [
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
    }
}
