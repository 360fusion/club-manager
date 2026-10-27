<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubVisitorAccess;
use App\Models\ClubVisitRequest;
use App\Services\Lodges\VisitorAccessService;
use App\Support\VisitorSummons;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A lodge's secretary deciding which visitors receive its summonses, and answering requests to visit a meeting.
 * The route is guarded by the "manage meetings" capability (see config/club_permissions.php).
 */
class VisitorAccessAdminController extends Controller
{
    public function __construct(private readonly VisitorAccessService $access) {}

    public function index(string $clubSlug): Response
    {
        $club = $this->findClub($clubSlug);

        $requests = ClubVisitorAccess::where('club_id', $club->id)->with(['user:id,name,email', 'decidedBy:id,name'])
            ->orderByRaw("case status when 'pending' then 0 when 'approved' then 1 else 2 end")
            ->latest('updated_at')->orderByDesc('id')
            ->paginate(20)->withQueryString()
            ->through(fn (ClubVisitorAccess $access) => [
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

        // shortcut: the latest 100 (waiting first, then by date) without paging; page them if a lodge ever has more.
        $visits = ClubVisitRequest::where('club_id', $club->id)->with(['user:id,name,email', 'decidedBy:id,name'])
            ->whereDate('meeting_date', '>=', now()->subDays(30)->toDateString())
            ->orderByRaw("case status when 'pending' then 0 else 1 end")->orderBy('meeting_date')->orderBy('id')
            ->limit(100)->get()
            ->map(fn (ClubVisitRequest $visit) => [
                'id' => $visit->id,
                'status' => $visit->status,
                'meeting_date' => $visit->meeting_date->toDateString(),
                'name' => $visit->user->name,
                'email' => $visit->user->email,
                'home_lodge_name' => $visit->home_lodge_name,
                'home_lodge_number' => $visit->home_lodge_number,
                'rank' => $visit->rank,
                'dining' => $visit->dining,
                'message' => $visit->message,
                'reply' => $visit->reply,
                'decided_by' => $visit->decidedBy?->name,
                'requested_at' => $visit->created_at->toDateString(),
            ]);

        return Inertia::render('Admin/Visitors/Index', [
            'club' => $club->only(['id', 'name', 'slug']),
            'visits' => $visits,
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

    public function answerVisit(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $visit = ClubVisitRequest::where('club_id', $club->id)->with('user')->findOrFail($id);
        $validated = $request->validate(['accept' => 'required|boolean', 'reply' => 'nullable|string|max:1000']);

        $this->access->answerVisit($visit, $request->user(), (bool) $validated['accept'], $validated['reply'] ?? null);

        return back()->with('success', $validated['accept'] ? 'Accepted. He has been told.' : 'Declined. He has been told.');
    }

    /**
     * Always looked up inside this club, so an id from another club's list finds nothing.
     */
    private function find(string $clubSlug, int $id): ClubVisitorAccess
    {
        $club = $this->findClub($clubSlug);

        return ClubVisitorAccess::where('club_id', $club->id)->with('user')->findOrFail($id);
    }
}
