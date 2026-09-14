<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Newsletter;
use App\Models\NewsletterSubscription;
use App\Models\NewsletterType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MemberSubscriptionsController extends Controller
{
    /**
     * Display member multi-club subscriptions management page & unified news feed.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $userClubs = $user->clubs()->get();

        // Ensure default types exist for user clubs
        foreach ($userClubs as $club) {
            NewsletterTypeAdminController::ensureDefaultTypes($club);
        }

        // Gather all clubs the user has any interaction with (enrolled + subscribed)
        $userClubIds = $userClubs->pluck('id')->toArray();
        $subscribedClubIds = NewsletterSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->pluck('club_id')
            ->toArray();

        $allClubIds = array_unique(array_merge($userClubIds, $subscribedClubIds));
        $allClubs = Club::whereIn('id', $allClubIds)->with('newsletterTypes')->get();

        // User active subscriptions
        $userSubscriptions = NewsletterSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->get()
            ->keyBy(fn ($item) => $item->club_id . '_' . $item->newsletter_type_id);

        $clubMatrix = $allClubs->map(function ($club) use ($user, $userSubscriptions) {
            $isMember = $user->clubs()->where('clubs.id', $club->id)->exists();

            $channels = $club->newsletterTypes->map(function ($type) use ($club, $isMember, $userSubscriptions) {
                $key = $club->id . '_' . $type->id;
                $hasExplicitSub = isset($userSubscriptions[$key]);
                
                // Active status: mandatory channels for internal members are active by default, or if explicitly subscribed
                $isActive = ($isMember && $type->is_mandatory) || $hasExplicitSub;

                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'description' => $type->description,
                    'color' => $type->color,
                    'icon' => $type->icon,
                    'is_mandatory' => $type->is_mandatory && $isMember,
                    'is_active' => $isActive,
                ];
            });

            return [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
                'lodge_number' => $club->lodge_number,
                'is_member' => $isMember,
                'channels' => $channels,
            ];
        });

        // Unified Broadcast Feed across member clubs
        $broadcastFeed = Newsletter::whereIn('club_id', $allClubIds)
            ->where('status', 'sent')
            ->with(['club', 'newsletterType'])
            ->orderByDesc('sent_at')
            ->limit(15)
            ->get()
            ->map(function ($n) {
                return [
                    'id' => $n->id,
                    'club_name' => $n->club->name,
                    'club_slug' => $n->club->slug,
                    'channel_name' => $n->newsletterType?->name ?? 'Broadcast',
                    'channel_color' => $n->newsletterType?->color ?? '#4f46e5',
                    'channel_icon' => $n->newsletterType?->icon ?? '✉️',
                    'subject' => $n->subject,
                    'content' => $n->content,
                    'sent_at' => $n->sent_at?->format('M d, Y @ H:i'),
                ];
            });

        return Inertia::render('Portal/Subscriptions', [
            'clubMatrix' => $clubMatrix,
            'broadcastFeed' => $broadcastFeed,
            'user' => $user,
        ]);
    }

    /**
     * Toggle subscription preference for a specific club and channel.
     */
    public function toggle(Request $request, string $clubSlug, int $typeId): RedirectResponse
    {
        $user = Auth::user();
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $type = NewsletterType::where('club_id', $club->id)->findOrFail($typeId);

        $existing = NewsletterSubscription::where('club_id', $club->id)
            ->where('newsletter_type_id', $type->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            $newStatus = $existing->status === 'active' ? 'unsubscribed' : 'active';
            $existing->update([
                'status' => $newStatus,
                'subscribed_at' => $newStatus === 'active' ? now() : $existing->subscribed_at,
                'unsubscribed_at' => $newStatus === 'unsubscribed' ? now() : null,
            ]);
        } else {
            NewsletterSubscription::create([
                'club_id' => $club->id,
                'newsletter_type_id' => $type->id,
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'rank' => $user->rank ?? null,
                'status' => 'active',
                'subscribed_at' => now(),
            ]);
        }

        return redirect()->back()->with('success', 'Subscription preferences updated.');
    }
}
