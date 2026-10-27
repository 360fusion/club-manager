<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MediaUploadChecks;
use App\Http\Controllers\Concerns\TransformsMedia;
use App\Http\Requests\MediaAdmin\BulkDeleteRequest;
use App\Models\Accounting\Bill;
use App\Models\Club;
use App\Models\Invoice;
use App\Models\Post;
use App\Support\ClubAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bulk delete and bulk move of media items, plus where an item is used. Split out of MediaAdminController; the URLs, route names and permissions are unchanged.
 */
class MediaBulkController extends Controller
{
    use MediaUploadChecks;
    use TransformsMedia;

    /**
     * Bulk delete multiple media items.
     */
    public function bulkDelete(string $clubSlug, BulkDeleteRequest $request): JsonResponse
    {
        $club = $this->findClub($clubSlug);

        $count = 0;
        $skippedProtected = 0;
        foreach ($request->input('ids') as $mediaId) {
            $media = $club->media()->find($mediaId);
            if ($media) {
                if ($this->isAccountingProtected($media)) {
                    $skippedProtected++;

                    continue;
                }
                $media->delete();
                $count++;
            }
        }

        if ($count === 0 && $skippedProtected > 0) {
            return response()->json([
                'success' => false,
                'message' => 'The selected file(s) are attached to Accounting records and protected by the financial audit trail. They cannot be deleted from the file manager.',
            ], 422);
        }

        $message = "Successfully deleted {$count} ".($count === 1 ? 'file' : 'files').'.';
        if ($skippedProtected > 0) {
            $message .= " ({$skippedProtected} accounting-protected ".($skippedProtected === 1 ? 'file was' : 'files were').' skipped).';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    /**
     * Bulk move multiple media items to a target folder / collection.
     */
    public function bulkMove(string $clubSlug, Request $request): JsonResponse
    {
        $club = $this->findClub($clubSlug);
        $request->validate([
            'ids' => 'required|array|max:500',
            'ids.*' => 'integer',
            'folder' => ['required', 'string', $this->folderRule($club)],
        ]);

        $targetFolder = $request->input('folder');
        $count = 0;
        $skippedProtected = 0;

        foreach ($request->input('ids') as $mediaId) {
            $media = $club->media()->find($mediaId);
            if ($media) {
                if ($this->isAccountingProtected($media)) {
                    $skippedProtected++;

                    continue;
                }
                $media->collection_name = $targetFolder;
                $media->save();
                $count++;
            }
        }

        $message = "Successfully moved {$count} ".($count === 1 ? 'file' : 'files')." to '{$targetFolder}'.";
        if ($skippedProtected > 0) {
            $message .= " ({$skippedProtected} accounting-protected ".($skippedProtected === 1 ? 'file was' : 'files were').' skipped).';
        }

        return response()->json([
            'success' => $count > 0 || $skippedProtected === 0,
            'message' => $message,
        ]);
    }

    /**
     * Check asset usage across news posts, club branding settings, and accounting records.
     */
    public function usage(string $clubSlug, int $id): JsonResponse
    {
        $club = $this->findClub($clubSlug);
        $media = $club->media()->withTrashed()->findOrFail($id);

        if ($this->isAccountingProtected($media) && ! ClubAccess::can(auth()->user(), $club, 'manage_billing')) {
            abort(403, 'Your role does not have access to the Accounting area.');
        }

        $url = $media->getFullUrl();
        $filename = $media->file_name;
        $usages = [];

        // Check if used in Club Logo
        if ($club->logo_url && (str_contains($club->logo_url, $filename) || $club->logo_url === $url)) {
            $usages[] = [
                'type' => 'Club Logo',
                'title' => $club->name.' Logo',
                'location' => 'Club Settings',
            ];
        }

        // Check if used in News Articles / Posts
        $posts = Post::where('club_id', $club->id)->get();
        foreach ($posts as $post) {
            if ($post->cover_image && (str_contains($post->cover_image, $filename) || $post->cover_image === $url)) {
                $usages[] = [
                    'type' => 'Cover Image',
                    'title' => $post->title,
                    'location' => 'News Article Cover',
                ];
            }
            if ($post->content && str_contains($post->content, $filename)) {
                $usages[] = [
                    'type' => 'Article Content',
                    'title' => $post->title,
                    'location' => 'News Article Body',
                ];
            }
        }

        // Check if attached to Accounting Bills
        $bills = Bill::where('club_id', $club->id)->where('media_id', $media->id)->get();
        foreach ($bills as $bill) {
            $usages[] = [
                'type' => 'Accounting Bill Receipt',
                'title' => "Bill {$bill->bill_number} ({$bill->vendor_name})",
                'location' => 'Accounting ERP → Purchases',
            ];
        }

        // Check if attached to Accounting Invoices
        $invoices = Invoice::where('club_id', $club->id)->where('media_id', $media->id)->get();
        foreach ($invoices as $inv) {
            $usages[] = [
                'type' => 'Member Invoice Document',
                'title' => "Invoice {$inv->invoice_number} ({$inv->title})",
                'location' => 'Accounting ERP → Sales',
            ];
        }

        return response()->json([
            'usage_count' => count($usages),
            'usages' => $usages,
        ]);
    }
}
