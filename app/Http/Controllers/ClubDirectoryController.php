<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\NewsletterSubscription;
use App\Models\NewsletterType;
use App\Services\ClubDirectoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ClubDirectoryController extends Controller
{
    /**
     * The searchable national directory of lodges and clubs.
     */
    public function index(Request $request, ClubDirectoryService $directory): Response
    {
        return Inertia::render('Members/Directory', [
            'directory' => $directory->listing(
                (string) $request->query('search', ''),
                (string) $request->query('region', ''),
            ),
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
