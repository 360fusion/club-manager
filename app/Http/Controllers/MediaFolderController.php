<?php

namespace App\Http\Controllers;

use App\Http\Requests\MediaAdmin\StoreFolderRequest;
use App\Http\Requests\MediaFolder\UpdateFolderRequest;
use App\Support\MediaFolders;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * The folders of the file manager. Split out of MediaAdminController; the URLs, route names and permissions are unchanged.
 */
class MediaFolderController extends Controller
{
    /**
     * Create a club-defined folder. Its slug becomes the media collection_name once
     * files are uploaded into it, so every existing folder/listing code works unchanged.
     */
    public function storeFolder(string $clubSlug, StoreFolderRequest $request): JsonResponse
    {
        $club = $this->findClub($clubSlug);

        $name = trim($request->input('name'));
        $slug = Str::slug($name);
        $parent = $request->input('parent');

        if ($slug === '') {
            return response()->json([
                'message' => 'Please use a folder name with at least one letter or number.',
                'errors' => ['name' => ['Please use a folder name with at least one letter or number.']],
            ], 422);
        }

        if (in_array($slug, MediaFolders::RESERVED_WORDS, true)) {
            return response()->json([
                'message' => "\"{$name}\" is a reserved folder name.",
                'errors' => ['name' => ['This name is reserved.']],
            ], 422);
        }

        if ($club->mediaFolders()->where('slug', $slug)->exists()) {
            return response()->json([
                'message' => 'A folder with this name already exists.',
                'errors' => ['name' => ['A folder with this name already exists.']],
            ], 422);
        }

        $folder = $club->mediaFolders()->create([
            'name' => $name,
            'slug' => $slug,
            'parent_slug' => $parent,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'folder' => $folder->only(['id', 'name', 'slug', 'parent_slug']),
        ]);
    }

    /**
     * Rename a club-defined folder. The slug (and therefore existing files'
     * collection_name) never changes, so nothing needs to be rewritten.
     */
    public function updateFolder(string $clubSlug, int $folderId, UpdateFolderRequest $request): JsonResponse
    {
        $club = $this->findClub($clubSlug);
        $folder = $club->mediaFolders()->findOrFail($folderId);

        $folder->name = trim($request->input('name'));
        $folder->save();

        return response()->json([
            'success' => true,
            'folder' => $folder->only(['id', 'name', 'slug', 'parent_slug']),
        ]);
    }

    /**
     * Delete a club-defined folder. Refuses if any file (including trashed) still
     * lives in it, so a folder can never be deleted out from under its contents.
     */
    public function destroyFolder(string $clubSlug, int $folderId): JsonResponse
    {
        $club = $this->findClub($clubSlug);
        $folder = $club->mediaFolders()->findOrFail($folderId);

        $hasFiles = $club->media()->withTrashed()->where('collection_name', $folder->slug)->exists();
        if ($hasFiles) {
            return response()->json([
                'success' => false,
                'message' => "\"{$folder->name}\" still has files in it. Move or delete them first.",
            ], 422);
        }

        $folder->delete();

        return response()->json([
            'success' => true,
            'message' => "Folder \"{$folder->name}\" deleted.",
        ]);
    }
}
