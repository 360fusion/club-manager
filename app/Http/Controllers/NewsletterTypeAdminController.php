<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\NewsletterSubscription;
use App\Models\NewsletterType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class NewsletterTypeAdminController extends Controller
{
    /**
     * Ensure default newsletter types exist for a club.
     */
    public static function ensureDefaultTypes(Club $club): void
    {
        if ($club->newsletterTypes()->count() > 0) {
            return;
        }

        NewsletterType::create([
            'club_id' => $club->id,
            'name' => 'Meeting Summonses',
            'slug' => 'meeting-summonses',
            'description' => 'Official meeting summonses and agenda circulars sent to members and visiting brethren.',
            'color' => '#4f46e5',
            'icon' => '📜',
            'is_external_subscribable' => true,
            'require_approval' => false,
            'is_mandatory' => true,
            'require_home_club_info' => true,
            'default_roles' => ['member', 'admin', 'treasurer'],
        ]);

        NewsletterType::create([
            'club_id' => $club->id,
            'name' => 'General News & Circulars',
            'slug' => 'general-news',
            'description' => 'General club announcements, master notes, and social updates.',
            'color' => '#059669',
            'icon' => '📰',
            'is_external_subscribable' => false,
            'require_approval' => false,
            'is_mandatory' => false,
            'require_home_club_info' => false,
            'default_roles' => ['member', 'admin', 'coach'],
        ]);

        NewsletterType::create([
            'club_id' => $club->id,
            'name' => 'Events & Social Bulletins',
            'slug' => 'events-bulletin',
            'description' => 'Social event announcements, annual gala invitations, and dining circulars.',
            'color' => '#d97706',
            'icon' => '🎟️',
            'is_external_subscribable' => true,
            'require_approval' => false,
            'is_mandatory' => false,
            'require_home_club_info' => true,
            'default_roles' => ['member'],
        ]);
    }

    /**
     * Display listing of newsletter types / channels.
     */
    public function index(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        self::ensureDefaultTypes($club);

        $types = NewsletterType::where('club_id', $club->id)
            ->withCount([
                'subscriptions as total_subscribers',
                'subscriptions as external_subscribers' => function ($query) {
                    $query->whereNull('user_id')->where('status', 'active');
                },
                'subscriptions as pending_approvals' => function ($query) {
                    $query->where('status', 'pending_approval');
                },
            ])
            ->get();

        return Inertia::render('Admin/Newsletters/Types', [
            'club' => $club,
            'types' => $types,
        ]);
    }

    /**
     * Store or update a newsletter type.
     */
    public function store(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'required|string|max:20',
            'icon' => 'required|string|max:10',
            'is_external_subscribable' => 'required|boolean',
            'require_approval' => 'required|boolean',
            'is_mandatory' => 'required|boolean',
            'require_home_club_info' => 'required|boolean',
            'default_roles' => 'nullable|array',
            'sender_name' => 'nullable|string|max:255',
            'sender_email' => 'nullable|email|max:255',
        ]);

        $slug = Str::slug($validated['name']);

        NewsletterType::updateOrCreate(
            ['id' => $validated['id'] ?? null, 'club_id' => $club->id],
            [
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? '',
                'color' => $validated['color'],
                'icon' => $validated['icon'],
                'is_external_subscribable' => $validated['is_external_subscribable'],
                'require_approval' => $validated['require_approval'],
                'is_mandatory' => $validated['is_mandatory'],
                'require_home_club_info' => $validated['require_home_club_info'],
                'default_roles' => $validated['default_roles'] ?? ['member'],
                'sender_name' => $validated['sender_name'] ?? null,
                'sender_email' => $validated['sender_email'] ?? null,
            ]
        );

        return redirect()->back()->with('success', 'Newsletter channel saved successfully.');
    }

    /**
     * Delete a custom newsletter type.
     */
    public function destroy(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $type = NewsletterType::where('club_id', $club->id)->findOrFail($id);
        $type->delete();

        return redirect()->back()->with('success', 'Newsletter channel deleted.');
    }

    /**
     * Display subscriber roster and pending approval queue.
     */
    public function subscribers(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        self::ensureDefaultTypes($club);

        $types = NewsletterType::where('club_id', $club->id)->get();

        $subscriptions = NewsletterSubscription::where('club_id', $club->id)
            ->with(['newsletterType', 'user'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($sub) {
                return [
                    'id' => $sub->id,
                    'newsletter_type_id' => $sub->newsletter_type_id,
                    'type_name' => $sub->newsletterType?->name ?? 'General',
                    'type_color' => $sub->newsletterType?->color ?? '#6366f1',
                    'type_icon' => $sub->newsletterType?->icon ?? '✉️',
                    'name' => $sub->name ?: ($sub->user?->name ?? 'External Subscriber'),
                    'email' => $sub->email,
                    'rank' => $sub->rank ?: ($sub->user?->rank ?? ''),
                    'home_club_name' => $sub->home_club_name ?: ($sub->user?->home_club_name ?? ''),
                    'home_club_number' => $sub->home_club_number ?: '',
                    'is_internal_member' => (bool) $sub->user_id,
                    'status' => $sub->status,
                    'subscribed_at' => $sub->subscribed_at?->format('M d, Y') ?? $sub->created_at->format('M d, Y'),
                ];
            });

        return Inertia::render('Admin/Newsletters/Subscribers', [
            'club' => $club,
            'types' => $types,
            'subscriptions' => $subscriptions,
        ]);
    }

    /**
     * Update subscription status (approve, reject, unsubscribe).
     */
    public function updateSubscriberStatus(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $subscription = NewsletterSubscription::where('club_id', $club->id)->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:active,pending_approval,unsubscribed,rejected',
        ]);

        $subscription->update([
            'status' => $validated['status'],
            'subscribed_at' => $validated['status'] === 'active' ? now() : $subscription->subscribed_at,
            'unsubscribed_at' => $validated['status'] === 'unsubscribed' ? now() : null,
        ]);

        return redirect()->back()->with('success', 'Subscriber status updated.');
    }
}
