<?php

namespace Launchpad\Http\Controllers\Workspaces;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Number;
use Illuminate\View\View;
use Launchpad\Files\Files;
use Launchpad\Http\Ui;
use Launchpad\Models\WorkspaceFile;
use Launchpad\Workspaces\Ability;
use Launchpad\Workspaces\CurrentWorkspace;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The Files page of a workspace: any member may add and download; the person who added a file, or whoever manages the workspace, may delete it */
class FileController extends Controller
{
    public function index(CurrentWorkspace $current, Files $files): View|Responsable
    {
        $workspace = $current->workspace;
        // shortcut: the newest 100 only, paginate when a team keeps more
        $rows = WorkspaceFile::with('uploader')->latest('id')->limit(100)->get();
        $used = $files->used($workspace);
        $quota = $files->quota($workspace);
        $manages = $current->can(Ability::ManageWorkspace);
        $userId = request()->user()->getKey();

        $summary = $quota === null
            ? __(':used used', ['used' => Number::fileSize($used, precision: 1)])
            : __(':used of :quota used', ['used' => Number::fileSize($used, precision: 1), 'quota' => Number::fileSize($quota, precision: 0)]);

        return Ui::render('workspaces.files', [
            'workspace' => $workspace, 'files' => $rows, 'summary' => $summary, 'manages' => $manages, 'userId' => $userId,
            'accept' => '.'.implode(',.', (array) config('launchpad.files.mimes')),
        ], fn () => [
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug],
            'files' => $rows->map(fn (WorkspaceFile $f) => [
                'id' => $f->id, 'name' => $f->name, 'size' => Number::fileSize($f->size, precision: 1), 'by' => $f->uploader?->name,
                'at' => $f->created_at, 'canDelete' => $manages || $f->uploaded_by === $userId,
            ])->values(),
            'summary' => $summary,
            'accept' => '.'.implode(',.', (array) config('launchpad.files.mimes')),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $current, Files $files): RedirectResponse
    {
        $request->validate(['file' => Files::rules()]);
        $stored = $files->store($current->workspace, $request->file('file'), $request->user());

        return back()->with('status', __(':name added.', ['name' => $stored->name]));
    }

    public function download(string $slug, CurrentWorkspace $current, string $file): StreamedResponse
    {
        return WorkspaceFile::findOrFail($file)->download();
    }

    public function destroy(string $slug, CurrentWorkspace $current, string $file): RedirectResponse
    {
        $row = WorkspaceFile::findOrFail($file);
        abort_unless($current->can(Ability::ManageWorkspace) || $row->uploaded_by === request()->user()->getKey(), 403);
        $row->delete();

        return back()->with('status', __('File deleted.'));
    }

    /** The link made by WorkspaceFile::signedUrl(): the signature is the permission, so there is no sign-in and no workspace */
    public function signed(string $file): StreamedResponse
    {
        return WorkspaceFile::findOrFail($file)->download();
    }
}
