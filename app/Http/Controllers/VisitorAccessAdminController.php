<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubVisitorAccess;
use App\Services\Lodges\VisitorAccessService;
use App\Support\VisitorSummons;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A lodge's secretary deciding which visitors receive its summonses. The route is guarded by the
 * "manage meetings" capability (see config/club_permissions.php).
 */
class VisitorAccessAdminController extends Controller
{
    public function __construct(private readonly VisitorAccessService $access) {}

    public function index(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $requests = ClubVisitorAccess::where('club_id', $club->id)->with(['user:id,name,email', 'decidedBy:id,name'])
            ->orderByRaw("case status when 'pending' then 0 when 'approved' then 1 else 2 end")
            ->latest('updated_at')->get()
            ->map(fn (ClubVisitorAccess $access) => [
                'id' => $access->id,
                'status' => $access->status,
                'name' => $access->user->name,
                'email' => $access->user->email,
                'home_lodge_name' => $access->home_lodge_name,
                'home_lodge_number' => $access->home_lodge_number,
                'rank' => $access->rank,
                'message' => $access->message,
                'decided_by' => $access->decidedBy?->name,
                'decided_at' => $access->decided_at?->toDateString(),
                'requested_at' => $access->created_at->toDateString(),
            ]);

        return Inertia::render('Admin/Visitors/Index', [
            'club' => $club->only(['id', 'name', 'slug']),
            'requests' => $requests,
            'visibility' => VisitorSummons::visibility($club),
            'visibilityOptions' => VisitorSummons::VISIBILITIES,
        ]);
    }

    public function approve(string $clubSlug, int $id): RedirectResponse
    {
        $this->access->approve($this->find($clubSlug, $id), auth()->user());

        return back()->with('success', 'Approved. They can now see your meetings on your lodge page.');
    }

    public function decline(string $clubSlug, int $id): RedirectResponse
    {
        $this->access->decline($this->find($clubSlug, $id), auth()->user());

        return back()->with('success', 'The request was declined.');
    }

    public function revoke(string $clubSlug, int $id): RedirectResponse
    {
        $this->access->revoke($this->find($clubSlug, $id), auth()->user());

        return back()->with('success', 'Access removed.');
    }

    /**
     * Always looked up inside this club, so an id from another club's list finds nothing.
     */
    private function find(string $clubSlug, int $id): ClubVisitorAccess
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        return ClubVisitorAccess::where('club_id', $club->id)->with('user')->findOrFail($id);
    }
}
