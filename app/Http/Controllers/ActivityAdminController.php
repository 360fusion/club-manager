<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Launchpad\Models\WorkspaceEvent;

class ActivityAdminController extends Controller
{
    /**
     * The club's activity log: Launchpad records it for the club as a team (see ClubUser).
     */
    public function index(string $clubSlug): Response
    {
        $club = $this->findClub($clubSlug);

        // the team scope follows Launchpad's current team, which club pages do not set
        $events = WorkspaceEvent::withoutGlobalScopes()->where('workspace_id', $club->id)
            ->orderByDesc('id')
            ->paginate(25)->withQueryString()
            ->through(fn (WorkspaceEvent $event) => [
                'id' => $event->id,
                'by' => $event->actor_name,
                'summary' => $event->summary,
                'at' => $event->created_at->toIso8601String(),
            ]);

        return Inertia::render('Admin/Activity/Index', [
            'club' => $club->only(['id', 'name', 'slug']),
            'events' => $events,
        ]);
    }
}
