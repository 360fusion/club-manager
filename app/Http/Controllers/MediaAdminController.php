<?php

namespace App\Http\Controllers;

use App\Models\Club;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class MediaAdminController extends Controller
{
    /**
     * Render full page File Manager view.
     */
    public function page(string $clubSlug): InertiaResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        return Inertia::render('Admin/Media/Index', [
            'club' => $club,
        ]);
    }
    /**
     * List media items for a club, filtered by folder collection and search query.
     */
    public function index(string $clubSlug, Request $request): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $folder = $request->query('folder', 'all');
        $search = $request->query('search', '');

        $query = $club->media()->orderByDesc('id');

        if ($folder && $folder !== 'all') {
            $query->where('collection_name', $folder);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('file_name', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $mediaItems = $query->get()->map(function ($media) {
            return [
                'id' => $media->id,
                'name' => $media->name,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'human_size' => $this->formatBytes($media->size),
                'collection_name' => $media->collection_name,
                'original_url' => $media->getFullUrl(),
                'created_at' => $media->created_at->format('M d, Y H:i'),
            ];
        });

        return response()->json([
            'media' => $mediaItems,
        ]);
    }

    /**
     * Upload a new file directly into the specified club media collection / folder.
     */
    public function store(string $clubSlug, Request $request): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $request->validate([
            'file' => 'required|file|max:20480',
            'folder' => 'required|string|in:logos,news,newsletters,images,galleries,documents',
        ]);

        $folder = $request->input('folder', 'images');

        $media = $club->addMediaFromRequest('file')
            ->usingName(pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME))
            ->toMediaCollection($folder);

        return response()->json([
            'success' => true,
            'media' => [
                'id' => $media->id,
                'name' => $media->name,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'human_size' => $this->formatBytes($media->size),
                'collection_name' => $media->collection_name,
                'original_url' => $media->getFullUrl(),
                'created_at' => $media->created_at->format('M d, Y H:i'),
            ],
        ]);
    }

    /**
     * Delete a media item.
     */
    public function destroy(string $clubSlug, int $id): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $media = $club->media()->findOrFail($id);

        $media->delete();

        return response()->json([
            'success' => true,
            'message' => 'File deleted successfully from media library.',
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        return round($bytes / 1024, 1) . ' KB';
    }
}
