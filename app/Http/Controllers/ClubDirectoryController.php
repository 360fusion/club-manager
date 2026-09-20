<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\NewsletterSubscription;
use App\Models\NewsletterType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ClubDirectoryController extends Controller
{
    /**
     * Display searchable National Club & Lodge Directory.
     */
    public function index(Request $request): Response
    {
        $search = $request->query('search', '');
        $region = $request->query('region', '');

        $query = Club::query()
            ->where('is_directory_listed', true)
            ->where('status', 'active')
            ->with(['newsletterTypes' => function ($q) {
                $q->where('is_external_subscribable', true);
            }]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('lodge_number', 'ilike', "%{$search}%")
                    ->orWhere('town_city', 'ilike', "%{$search}%")
                    ->orWhere('province_region', 'ilike', "%{$search}%");
            });
        }

        if ($region) {
            $query->where('province_region', $region);
        }

        $clubs = $query->orderBy('name', 'asc')->get()->map(function ($club) {
            return [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
                'lodge_number' => $club->lodge_number,
                'province_region' => $club->province_region ?: 'General',
                'town_city' => $club->town_city ?: 'Oxford',
                'logo_url' => $club->logo_url,
                'subscribable_types' => $club->newsletterTypes->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'description' => $t->description,
                    'color' => $t->color,
                    'icon' => $t->icon,
                    'require_approval' => $t->require_approval,
                    'require_home_club_info' => $t->require_home_club_info,
                ]),
            ];
        });

        $regions = Club::whereNotNull('province_region')
            ->distinct()
            ->pluck('province_region');

        return Inertia::render('Directory/Index', [
            'clubs' => $clubs,
            'regions' => $regions,
            'filters' => [
                'search' => $search,
                'region' => $region,
            ],
            'user' => Auth::user(),
        ]);
    }

    /**
     * Subscribe to a club's subscribable newsletter channel.
     */
    public function subscribe(Request $request, string $clubSlug, int $typeId): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $type = NewsletterType::where('club_id', $club->id)
            ->where('is_external_subscribable', true)
            ->findOrFail($typeId);

        $user = Auth::user();

        $validated = $request->validate([
            'email' => $user ? 'nullable|email' : 'required|email',
            'name' => 'nullable|string|max:255',
            'rank' => 'nullable|string|max:100',
            'home_club_name' => 'nullable|string|max:255',
            'home_club_number' => 'nullable|string|max:50',
        ]);

        $email = $user ? $user->email : $validated['email'];
        $name = $user ? $user->name : ($validated['name'] ?? null);
        $rank = $user ? ($user->rank ?? null) : ($validated['rank'] ?? null);
        $homeClubName = $validated['home_club_name'] ?? null;
        $homeClubNumber = $validated['home_club_number'] ?? null;

        $status = $type->require_approval ? 'pending_approval' : 'active';

        NewsletterSubscription::updateOrCreate(
            [
                'club_id' => $club->id,
                'newsletter_type_id' => $type->id,
                'email' => $email,
            ],
            [
                'user_id' => $user?->id,
                'name' => $name,
                'rank' => $rank,
                'home_club_name' => $homeClubName,
                'home_club_number' => $homeClubNumber,
                'status' => $status,
                'subscribed_at' => $status === 'active' ? now() : null,
            ]
        );

        $msg = $status === 'pending_approval'
            ? 'Subscription request submitted! The Secretary will review your request.'
            : 'Successfully subscribed to '.$type->name.' for '.$club->name.'!';

        return redirect()->back()->with('success', $msg);
    }
}
