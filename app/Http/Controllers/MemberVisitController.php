<?php

namespace App\Http\Controllers;

use App\Enums\ClubMemberStatus;
use App\Enums\ClubRole;
use App\Models\Lodge;
use App\Models\MemberVisit;
use App\Services\Meetings\MeetingReplies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The visiting record: a member notes the lodges they visit (from My lodges or a lodge's directory page), and the
 * Secretary of each of their lodges sees where members have been visiting (/{club}/admin/meetings/visiting).
 */
class MemberVisitController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lodge_slug' => ['nullable', 'string', 'max:255'],
            'lodge_name' => ['required_without:lodge_slug', 'nullable', 'string', 'max:200'],
            'visited_on' => ['required', 'date', 'before_or_equal:today', 'after:1950-01-01'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $lodge = filled($validated['lodge_slug'] ?? null) ? Lodge::listed()->where('slug', $validated['lodge_slug'])->firstOrFail() : null;

        MemberVisit::create([
            'user_id' => $request->user()->id,
            'lodge_id' => $lodge?->id,
            'lodge_name' => $lodge?->displayName() ?? trim($validated['lodge_name']),
            'visited_on' => $validated['visited_on'],
            'note' => $validated['note'] ?? null,
        ]);

        return back()->with('success', 'Visit recorded.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        MemberVisit::where('user_id', $request->user()->id)->where('id', $id)->firstOrFail()->delete();

        return back();
    }

    /**
     * Where this lodge's members have visited in the last year, newest first. The member's own note stays private.
     */
    public function lodgeIndex(string $clubSlug): Response
    {
        $club = $this->findClub($clubSlug);
        $members = $club->users()
            ->wherePivot('status', ClubMemberStatus::Active->value)
            ->wherePivot('role', '!=', ClubRole::Visitor->value)
            ->pluck('users.id');
        $name = MeetingReplies::rankedNamer($club->id, $members);

        $visits = MemberVisit::whereIn('user_id', $members)
            ->where('visited_on', '>=', now()->subYear()->toDateString())
            ->with(['user', 'lodge:id,slug'])
            ->orderByDesc('visited_on')->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (MemberVisit $visit) => [
                'id' => $visit->id,
                'member' => $name($visit->user),
                'lodge' => $visit->lodge_name,
                'lodge_slug' => $visit->lodge?->slug,
                'visited_on' => $visit->visited_on->format('j M Y'),
            ]);

        return Inertia::render('Admin/Meetings/Visiting', [
            'club' => ['slug' => $club->slug, 'name' => $club->name],
            'visits' => $visits->values(),
            'visitors' => $visits->pluck('member')->unique()->count(),
        ]);
    }
}
