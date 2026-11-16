<?php

namespace Launchpad\Http\Controllers\Workspaces;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Launchpad\Media\Library;
use Launchpad\Models\MediaFolder;
use Launchpad\Workspaces\CurrentWorkspace;

/** Folders of the media library: any member may make, rename and move them; whoever made one, or manages the team, may delete it */
class MediaFolderController extends Controller
{
    public function __construct(private readonly CurrentWorkspace $current, private readonly Library $library, private readonly MediaController $media) {}

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:100'], 'parent_id' => ['nullable', 'integer']]);
        $folder = $this->library->makeFolder($this->current->workspace, $request->string('name'), $this->media->folder($request, 'parent_id'), $request->user());

        return $this->media->answer($request, $this->media->folderJson($folder), __(':name made.', ['name' => $folder->name]), 201);
    }

    public function update(Request $request, string $slug, string $folder): JsonResponse|RedirectResponse
    {
        $row = MediaFolder::findOrFail($folder);
        $request->validate(['name' => ['sometimes', 'required', 'string', 'max:100'], 'parent_id' => ['sometimes', 'nullable', 'integer']]);
        $parent = $request->has('parent_id') ? $this->media->folder($request, 'parent_id') : null;

        DB::transaction(function () use ($request, $row, $parent) {
            $request->has('name') && $this->library->renameFolder($row, $request->string('name'));
            $request->has('parent_id') && $this->library->moveFolder($row, $parent);
        });

        return $this->media->answer($request, $this->media->folderJson($row->refresh()), __('Saved.'));
    }

    public function trash(Request $request, string $slug, string $folder): JsonResponse|RedirectResponse
    {
        $row = MediaFolder::findOrFail($folder);
        $this->media->mayDelete($row->created_by);
        $this->library->trash($row);

        return $this->media->answer($request, $this->media->folderJson($row), __(':name moved to the trash.', ['name' => $row->name]));
    }

    public function restore(Request $request, string $slug, string $folder): JsonResponse|RedirectResponse
    {
        $row = MediaFolder::onlyTrashed()->findOrFail($folder);
        $this->media->mayDelete($row->created_by);
        $this->library->restore($row);

        return $this->media->answer($request, $this->media->folderJson($row->refresh()), __(':name restored.', ['name' => $row->name]));
    }

    public function destroy(Request $request, string $slug, string $folder): JsonResponse|RedirectResponse
    {
        $row = MediaFolder::onlyTrashed()->findOrFail($folder);
        $this->media->mayDelete($row->created_by);
        // what others added is not the folder's maker's to delete for good
        abort_unless($this->media->canManage() || ! $this->library->holdsOthersFiles($row, $request->user()->getKey()), 403);
        $this->library->destroy($row);

        return $this->media->answer($request, ['id' => $row->id], __(':name deleted for good.', ['name' => $row->name]));
    }
}
