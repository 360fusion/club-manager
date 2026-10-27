<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MediaUploadChecks;
use App\Http\Controllers\Concerns\TransformsMedia;
use App\Models\Club;
use App\Models\Media;
use App\Models\MediaVersion;
use App\Services\MediaVersions;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Version history of a media item: list, restore and download. Split out of MediaAdminController; the URLs, route names and permissions are unchanged.
 */
class MediaVersionController extends Controller
{
    use MediaUploadChecks;
    use TransformsMedia;

    public function __construct(
        protected MediaVersions $versions
    ) {}

    /**
     * List the stored version history of a media item, newest first.
     */
    public function versions(string $clubSlug, int $id): JsonResponse
    {
        $club = $this->findClub($clubSlug);
        $media = $club->media()->withTrashed()->findOrFail($id);

        if ($this->isAccountingProtected($media)) {
            return response()->json([
                'message' => "Protected Audit Document: '{$media->file_name}' is managed from the Accounting page.",
            ], 403);
        }

        $versions = $media->versions()->with('uploader:id,name')->get()->map(fn (MediaVersion $version) => $this->transformVersion($media, $version));

        return response()->json([
            'versions' => $versions,
        ]);
    }

    /**
     * Restore a media item's content to a previous version. The current content is
     * itself saved to history first, so a restore can always be undone. Because the
     * current content is kept rather than discarded, a restore always adds the
     * restored version's size to the club's storage usage — it is never "free".
     */
    public function restoreVersion(string $clubSlug, int $id, int $versionId): JsonResponse
    {
        $club = $this->findClub($clubSlug);
        $media = $club->media()->findOrFail($id);
        $version = $media->versions()->findOrFail($versionId);

        if ($this->isAccountingProtected($media)) {
            return response()->json([
                'success' => false,
                'message' => "Protected Audit Document: '{$media->file_name}' is attached to an Accounting bill or invoice. It cannot be edited from the file manager; manage or delete it directly from the Accounting page.",
            ], 422);
        }

        if ($quotaError = $this->assertQuotaAvailable($club, $version->size)) {
            return $quotaError;
        }

        $this->versions->snapshot($media, 'Before restoring version from '.$version->created_at->format('j M Y, H:i'), auth()->id());

        $liveDisk = Storage::disk($media->disk);
        $directory = dirname($media->getPathRelativeToRoot());
        $liveDisk->deleteDirectory($directory);
        $liveDisk->makeDirectory($directory);
        $liveDisk->put($directory.'/'.$version->file_name, Storage::disk($version->disk)->get($version->path));

        $media->file_name = $version->file_name;
        $media->mime_type = $version->mime_type;
        $media->size = $version->size;
        $media->save();

        return response()->json([
            'success' => true,
            'message' => 'File restored to the selected version.',
            'media' => $this->transformMedia($media->fresh()),
        ]);
    }

    /**
     * Stream a historical version's file to a signed-in club admin.
     */
    public function downloadVersion(string $clubSlug, int $id, int $versionId): BinaryFileResponse|JsonResponse
    {
        $club = $this->findClub($clubSlug);
        $media = $club->media()->withTrashed()->findOrFail($id);

        if ($this->isAccountingProtected($media)) {
            return response()->json([
                'message' => "Protected Audit Document: '{$media->file_name}' is managed from the Accounting page.",
            ], 403);
        }

        $version = $media->versions()->findOrFail($versionId);

        return response()->file(Storage::disk($version->disk)->path($version->path), [
            'Content-Type' => $version->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($version->file_name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function transformVersion(Media $media, MediaVersion $version): array
    {
        return [
            'id' => $version->id,
            'file_name' => $version->file_name,
            'mime_type' => $version->mime_type,
            'human_size' => Club::formatStorageBytes($version->size),
            'note' => $version->note,
            'uploaded_by' => $version->uploader?->name,
            'created_at' => $version->created_at->format('j M Y, H:i'),
            'download_url' => route('admin.media.versions.download', [
                'clubSlug' => Club::whereKey($media->model_id)->value('slug'),
                'id' => $media->id,
                'versionId' => $version->id,
            ]),
        ];
    }
}
