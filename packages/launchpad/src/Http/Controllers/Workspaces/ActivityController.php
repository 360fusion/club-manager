<?php

namespace Launchpad\Http\Controllers\Workspaces;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Launchpad\Http\Ui;
use Launchpad\Models\WorkspaceEvent;
use Launchpad\Workspaces\CurrentWorkspace;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** What has happened in the workspace, newest first: for whoever may manage it */
class ActivityController extends Controller
{
    public function index(CurrentWorkspace $current): View|Responsable
    {
        $workspace = $current->workspace;
        $events = WorkspaceEvent::latest('id')->simplePaginate(25);

        return Ui::render('workspaces.activity', ['workspace' => $workspace, 'events' => $events], fn () => [
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug],
            'events' => $events->getCollection()->map(fn (WorkspaceEvent $e) => ['id' => $e->id, 'text' => "{$e->actor_name} {$e->summary}", 'at' => $e->created_at])->values(),
            'newerUrl' => $events->previousPageUrl(),
            'olderUrl' => $events->nextPageUrl(),
            'downloadUrl' => route('workspace.activity.download', $workspace->slug),
        ]);
    }

    /** Every line still kept, as CSV, oldest first: for a business that keeps its own records or feeds a security tool */
    public function download(CurrentWorkspace $current): StreamedResponse
    {
        // a cell a spreadsheet would read as a formula is written as text
        $cell = fn (?string $value) => preg_match('/\A[=+\-@\t\r]/', (string) $value) ? "'".$value : (string) $value;
        $name = 'team-activity-'.$current->workspace->slug.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($cell) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['when_utc', 'who', 'action', 'what'], escape: '');
            WorkspaceEvent::orderBy('id')->lazyById(500)->each(fn (WorkspaceEvent $e) => fputcsv($out, [
                $e->created_at->utc()->toIso8601String(), $cell($e->actor_name), $e->action, $cell($e->summary),
            ], escape: ''));
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
