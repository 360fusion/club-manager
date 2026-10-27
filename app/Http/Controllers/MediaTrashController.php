<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\TransformsMedia;
use App\Http\Requests\MediaAdmin\BulkForceDeleteRequest;
use App\Http\Requests\MediaAdmin\BulkRestoreRequest;
use Illuminate\Http\JsonResponse;

/**
 * Deleted files: putting them back or removing them for good. Split out of MediaAdminController; the URLs, route names and permissions are unchanged.
 */
class MediaTrashController extends Controller
{
    use TransformsMedia;

    /**
     * Restore a soft-deleted media item from Trash bin.
     */
    public function restore(string $clubSlug, int $id): JsonResponse
    {
        $club = $this->findClub($clubSlug);
        $media = $club->media()->onlyTrashed()->findOrFail($id);

        $media->restore();

        return response()->json([
            'success' => true,
            'message' => "File '{$media->file_name}' restored successfully from Trash bin.",
            'media' => $this->transformMedia($media),
        ]);
    }

    /**
     * Permanently delete a media item from disk and database.
     */
    public function forceDelete(string $clubSlug, int $id): JsonResponse
    {
        $club = $this->findClub($clubSlug);
        $media = $club->media()->withTrashed()->findOrFail($id);

        if ($this->isAccountingProtected($media)) {
            return response()->json([
                'success' => false,
                'message' => "Protected Audit Document: '{$media->file_name}' is attached to an Accounting bill or invoice. It cannot be permanently deleted from the file manager; manage or delete it directly from the Accounting page.",
            ], 422);
        }

        $filename = $media->file_name;
        $media->forceDelete();

        return response()->json([
            'success' => true,
            'message' => "File '{$filename}' permanently deleted.",
        ]);
    }

    /**
     * Bulk restore multiple media items from Trash bin.
     */
    public function bulkRestore(string $clubSlug, BulkRestoreRequest $request): JsonResponse
    {
        $club = $this->findClub($clubSlug);

        $count = 0;
        foreach ($request->input('ids') as $mediaId) {
            $media = $club->media()->onlyTrashed()->find($mediaId);
            if ($media) {
                $media->restore();
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully restored {$count} ".($count === 1 ? 'file' : 'files').' from Trash.',
        ]);
    }

    /**
     * Bulk force-delete multiple media items permanently.
     */
    public function bulkForceDelete(string $clubSlug, BulkForceDeleteRequest $request): JsonResponse
    {
        $club = $this->findClub($clubSlug);

        $count = 0;
        $skippedProtected = 0;
        foreach ($request->input('ids') as $mediaId) {
            $media = $club->media()->onlyTrashed()->find($mediaId);
            if ($media) {
                if ($this->isAccountingProtected($media)) {
                    $skippedProtected++;

                    continue;
                }
                $media->forceDelete();
                $count++;
            }
        }

        if ($count === 0 && $skippedProtected > 0) {
            return response()->json([
                'success' => false,
                'message' => 'The selected file(s) are attached to Accounting records and protected by the financial audit trail. They cannot be permanently deleted from the file manager.',
            ], 422);
        }

        $message = "Successfully permanently deleted {$count} ".($count === 1 ? 'file' : 'files').'.';
        if ($skippedProtected > 0) {
            $message .= " ({$skippedProtected} accounting-protected ".($skippedProtected === 1 ? 'file was' : 'files were').' skipped).';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }
}
