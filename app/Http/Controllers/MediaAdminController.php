<?php

namespace App\Http\Controllers;

use App\Models\Accounting\Bill;
use App\Models\Club;
use App\Models\Invoice;
use App\Models\Post;
use App\Support\ImageDownscaler;
use App\Support\UploadRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
     * List media items for a club, filtered by folder collection, search query, type, extension, date, and sort order.
     */
    public function index(string $clubSlug, Request $request): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $folder = $request->query('folder', 'all');
        $search = $request->query('search', '');
        $type = $request->query('type', 'all');
        $extension = strtolower($request->query('extension', 'all'));
        $date = $request->query('date', 'all');
        $sort = $request->query('sort', 'newest');

        $trashCount = $club->media()->onlyTrashed()->count();

        // Fetch all non-trashed club media once to build metadata filters (available extensions & dates)
        $allClubMedia = $club->media()->get();

        $availableExtensions = $allClubMedia
            ->map(fn ($m) => strtolower(pathinfo($m->file_name, PATHINFO_EXTENSION)))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $availableMonths = $allClubMedia
            ->map(function ($m) {
                return [
                    'value' => $m->created_at->format('Y-m'),
                    'label' => $m->created_at->format('M Y'),
                ];
            })
            ->unique('value')
            ->sortByDesc('value')
            ->values()
            ->toArray();

        // Build filtered query
        if ($folder === 'trash') {
            $query = $club->media()->onlyTrashed();
        } else {
            $query = $club->media();
            if ($folder && $folder !== 'all') {
                $query->where('collection_name', $folder);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('file_name', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($type === 'image') {
            $query->where('mime_type', 'like', 'image/%');
        } elseif ($type === 'document') {
            $query->where('mime_type', 'not like', 'image/%');
        }

        if ($extension && $extension !== 'all') {
            $query->where('file_name', 'like', "%.{$extension}");
        }

        if ($date && $date !== 'all') {
            if (preg_match('/^\d{4}-\d{2}$/', $date)) {
                [$year, $month] = explode('-', $date);
                $query->whereYear('created_at', $year)
                    ->whereMonth('created_at', $month);
            }
        }

        // Apply Sorting
        switch ($sort) {
            case 'oldest':
                $query->orderBy('id', 'asc');
                break;
            case 'name_asc':
                $query->orderBy('file_name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('file_name', 'desc');
                break;
            case 'size_desc':
                $query->orderBy('size', 'desc');
                break;
            case 'size_asc':
                $query->orderBy('size', 'asc');
                break;
            case 'newest':
            default:
                $query->orderBy('id', 'desc');
                break;
        }

        $mediaItems = $query->get()->map(function ($media) {
            return $this->transformMedia($media);
        });

        return response()->json([
            'media' => $mediaItems,
            'trash_count' => $trashCount,
            'available_extensions' => $availableExtensions,
            'available_months' => $availableMonths,
        ]);
    }

    /**
     * Upload a new file directly into the specified club media collection / folder.
     */
    public function store(string $clubSlug, Request $request): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $folder = $request->input('folder', 'images');

        // Master allowed extensions & MIME types
        $imageExtensions = UploadRules::IMAGE_TYPES;
        $docExtensions = 'pdf,doc,docx,xls,xlsx,csv,ppt,pptx,txt,rtf,zip';
        $allAllowedExtensions = "{$imageExtensions},{$docExtensions}";

        // Enforce folder-specific rules: Image-only folders vs General folders
        $imageOnlyFolders = ['logos', 'images', 'galleries'];
        $allowedRule = in_array($folder, $imageOnlyFolders) ? $imageExtensions : $allAllowedExtensions;

        $request->validate([
            'folder' => 'required|string|in:logos,news,events,updates,newsletters,pages,images,galleries,documents,accounting,summons',
            'file' => [
                'required',
                'file',
                'max:10240', // 10MB limit (10240 KB)
                "mimes:{$allowedRule}",
                function (string $attribute, mixed $value, \Closure $fail) {
                    $size = $value instanceof UploadedFile && str_starts_with((string) $value->getMimeType(), 'image/') ? @getimagesize($value->getRealPath()) : null;

                    if ($size && max($size[0], $size[1]) > UploadRules::MAX_IMAGE_SIDE) {
                        $fail('Images may be at most '.UploadRules::MAX_IMAGE_SIDE.' pixels wide or tall.');
                    }
                },
            ],
        ], [
            'file.max' => 'The uploaded file exceeds the 10MB size limit.',
            'file.mimes' => in_array($folder, $imageOnlyFolders)
                ? 'Invalid file format for this folder. Only image files (JPG, PNG, GIF, WEBP) are allowed.'
                : 'Invalid file format. Allowed file types: JPG, PNG, GIF, WEBP, PDF, DOC, DOCX, XLS, XLSX, CSV, PPT, PPTX, TXT, RTF, ZIP.',
        ]);

        $uploadedFile = $request->file('file');
        $originalName = $uploadedFile->getClientOriginalName();
        $extension = strtolower($uploadedFile->getClientOriginalExtension());

        // Security Check 1: Block executable / script extensions anywhere in the filename (e.g. avatar.php.png)
        $dangerousExtensions = ['php', 'phar', 'phtml', 'php3', 'php4', 'php5', 'py', 'pl', 'cgi', 'exe', 'sh', 'bat', 'cmd', 'js', 'html', 'htm', 'vbs', 'jar', 'htaccess'];
        $filenameSegments = explode('.', strtolower($originalName));
        foreach ($filenameSegments as $segment) {
            if (in_array($segment, $dangerousExtensions)) {
                return response()->json([
                    'message' => 'Security check failed: Executable or script files are strictly prohibited.',
                    'errors' => ['file' => ['File contains forbidden file extensions.']],
                ], 422);
            }
        }

        // Security Check 2: SVG XSS Content Inspection
        if ($extension === 'svg') {
            $svgContent = file_get_contents($uploadedFile->getRealPath());
            if (preg_match('/<script|javascript:|onload=|onerror=|onclick=/i', $svgContent)) {
                return response()->json([
                    'message' => 'Security check failed: Malicious inline scripts detected inside SVG file.',
                    'errors' => ['file' => ['SVG contains forbidden script execution tags.']],
                ], 422);
            }
        }

        // Clean filename (strip invalid characters)
        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($originalName, PATHINFO_FILENAME));

        // Downscale large camera photos > 1920px max dimension before storing
        ImageDownscaler::apply($uploadedFile);

        $media = $club->addMediaFromRequest('file')
            ->usingName($safeName)
            ->toMediaCollection($folder);

        // Auto-generate human readable Alt Text from original filename
        $autoAltText = $this->formatTitleFromFilename($originalName);
        $media->setCustomProperty('alt_text', $autoAltText);
        $media->save();

        return response()->json([
            'success' => true,
            'media' => $this->transformMedia($media),
        ]);
    }

    /**
     * Update details (name, alt_text, caption) of a media item.
     */
    public function update(string $clubSlug, int $id, Request $request): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $media = $club->media()->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:1000',
        ]);

        $media->name = $request->input('name');
        $media->setCustomProperty('alt_text', $request->input('alt_text', ''));
        $media->setCustomProperty('caption', $request->input('caption', ''));
        $media->save();

        return response()->json([
            'success' => true,
            'message' => 'Media details updated successfully.',
            'media' => $this->transformMedia($media),
        ]);
    }

    /**
     * Crop an image media asset. Supports dual save modes:
     * - 'variant': Creates a separate new copy/variant asset, leaving original untouched.
     * - 'replace': Overwrites current asset while backing up the uncropped master original file for 1-click reverting.
     */
    public function crop(string $clubSlug, int $id, Request $request): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $media = $club->media()->findOrFail($id);

        $request->validate([
            'file' => UploadRules::image(10240, required: true),
            'save_mode' => 'nullable|string|in:replace,variant',
        ]);

        $saveMode = $request->input('save_mode', 'replace');
        $collection = $media->collection_name;

        if ($saveMode === 'variant') {
            $variantName = $media->name.' (Cropped)';
            $newMedia = $club->addMediaFromRequest('file')
                ->usingName($variantName)
                ->toMediaCollection($collection);

            $newMedia->setCustomProperty('alt_text', $media->getCustomProperty('alt_text', ''));
            $newMedia->setCustomProperty('caption', $media->getCustomProperty('caption', ''));
            $newMedia->setCustomProperty('is_variant', true);
            $newMedia->setCustomProperty('parent_media_id', $media->id);
            $newMedia->save();

            return response()->json([
                'success' => true,
                'message' => 'New cropped variant saved successfully.',
                'media' => $this->transformMedia($newMedia),
            ]);
        }

        // Mode: replace active asset while storing original master backup
        $originalFilePath = $media->getPath();
        $backupDir = storage_path("app/media-originals/{$club->id}");

        if (! file_exists($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $hasBackup = $media->getCustomProperty('has_original_backup', false);
        $masterBackupPath = $media->getCustomProperty('original_master_path', null);

        if (! $hasBackup || ! $masterBackupPath || ! file_exists($masterBackupPath)) {
            $ext = pathinfo($media->file_name, PATHINFO_EXTENSION) ?: 'png';
            $masterBackupPath = "{$backupDir}/{$media->id}_master.{$ext}";
            if (file_exists($originalFilePath)) {
                copy($originalFilePath, $masterBackupPath);
            }
        }

        $name = $media->name;
        $altText = $media->getCustomProperty('alt_text', '');
        $caption = $media->getCustomProperty('caption', '');

        $media->delete();

        $newMedia = $club->addMediaFromRequest('file')
            ->usingName($name)
            ->toMediaCollection($collection);

        $newMedia->setCustomProperty('alt_text', $altText);
        $newMedia->setCustomProperty('caption', $caption);
        $newMedia->setCustomProperty('has_original_backup', true);
        $newMedia->setCustomProperty('original_master_path', $masterBackupPath);
        $newMedia->setCustomProperty('is_cropped', true);
        $newMedia->setCustomProperty('cropped_at', now()->toIso8601String());
        $newMedia->save();

        return response()->json([
            'success' => true,
            'message' => 'Image updated successfully. Original master backup preserved.',
            'media' => $this->transformMedia($newMedia),
        ]);
    }

    /**
     * Revert a cropped media asset back to its original master image.
     */
    public function revert(string $clubSlug, int $id): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $media = $club->media()->findOrFail($id);

        $masterBackupPath = $media->getCustomProperty('original_master_path');

        if (! $masterBackupPath || ! file_exists($masterBackupPath)) {
            return response()->json([
                'success' => false,
                'message' => 'No original master backup found for this image.',
            ], 422);
        }

        $collection = $media->collection_name;
        $name = $media->name;
        $altText = $media->getCustomProperty('alt_text', '');
        $caption = $media->getCustomProperty('caption', '');

        $media->delete();

        $restoredMedia = $club->addMedia($masterBackupPath)
            ->preservingOriginal()
            ->usingName($name)
            ->toMediaCollection($collection);

        $restoredMedia->setCustomProperty('alt_text', $altText);
        $restoredMedia->setCustomProperty('caption', $caption);
        $restoredMedia->setCustomProperty('has_original_backup', true);
        $restoredMedia->setCustomProperty('original_master_path', $masterBackupPath);
        $restoredMedia->setCustomProperty('is_cropped', false);
        $restoredMedia->save();

        return response()->json([
            'success' => true,
            'message' => 'Image reverted to original master version successfully.',
            'media' => $this->transformMedia($restoredMedia),
        ]);
    }

    /**
     * Bulk delete multiple media items.
     */
    public function bulkDelete(string $clubSlug, Request $request): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $request->validate([
            'ids' => 'required|array|max:500',
            'ids.*' => 'integer',
        ]);

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
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $request->validate([
            'ids' => 'required|array|max:500',
            'ids.*' => 'integer',
            'folder' => 'required|string|in:logos,news,events,updates,newsletters,pages,images,galleries,documents,accounting,summons',
        ]);

        $targetFolder = $request->input('folder');
        $count = 0;

        foreach ($request->input('ids') as $mediaId) {
            $media = $club->media()->find($mediaId);
            if ($media) {
                $media->collection_name = $targetFolder;
                $media->save();
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully moved {$count} ".($count === 1 ? 'file' : 'files')." to '{$targetFolder}'.",
        ]);
    }

    /**
     * Check asset usage across news posts, club branding settings, and accounting records.
     */
    public function usage(string $clubSlug, int $id): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $media = $club->media()->withTrashed()->findOrFail($id);

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

    /**
     * Soft delete a media item (move to Trash bin).
     */
    public function destroy(string $clubSlug, int $id): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $media = $club->media()->findOrFail($id);

        if ($this->isAccountingProtected($media)) {
            return response()->json([
                'success' => false,
                'message' => "Protected Audit Document: '{$media->file_name}' is attached to an Accounting bill or invoice. It cannot be deleted from the file manager; manage or delete it directly from the Accounting page.",
            ], 422);
        }

        $media->delete();

        return response()->json([
            'success' => true,
            'message' => 'File moved to Trash bin.',
        ]);
    }

    /**
     * Restore a soft-deleted media item from Trash bin.
     */
    public function restore(string $clubSlug, int $id): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
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
        $club = Club::where('slug', $clubSlug)->firstOrFail();
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
    public function bulkRestore(string $clubSlug, Request $request): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $request->validate([
            'ids' => 'required|array|max:500',
            'ids.*' => 'integer',
        ]);

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
    public function bulkForceDelete(string $clubSlug, Request $request): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $request->validate([
            'ids' => 'required|array|max:500',
            'ids.*' => 'integer',
        ]);

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

    /**
     * Check if a media item is linked to an Accounting Bill or Invoice, or is accounting-protected.
     */
    private function isAccountingProtected($media): bool
    {
        if ($media->getCustomProperty('is_accounting_protected') || $media->getCustomProperty('source') === 'accounting' || $media->collection_name === 'accounting') {
            return true;
        }

        if (Bill::where('media_id', $media->id)->exists()) {
            return true;
        }

        if (Invoice::where('media_id', $media->id)->exists()) {
            return true;
        }

        return false;
    }

    private function formatTitleFromFilename(string $filename): string
    {
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        $clean = preg_replace('/[_\-\.]+/', ' ', $nameWithoutExt);

        return ucwords(trim($clean));
    }

    private function privateUrl($media): string
    {
        $slug = Club::whereKey($media->model_id)->value('slug');

        return $slug ? route('admin.accounting.attachments.show', ['clubSlug' => $slug, 'mediaId' => $media->id]) : '';
    }

    private function transformMedia($media): array
    {
        $isAccountingProtected = $this->isAccountingProtected($media);

        return [
            'id' => $media->id,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'human_size' => $this->formatBytes($media->size),
            'collection_name' => $media->collection_name,
            'original_url' => $media->disk === 'public' ? $media->getFullUrl() : $this->privateUrl($media),
            'alt_text' => $media->getCustomProperty('alt_text', ''),
            'caption' => $media->getCustomProperty('caption', ''),
            'has_original_backup' => (bool) ($media->getCustomProperty('has_original_backup', false) && file_exists($media->getCustomProperty('original_master_path', ''))),
            'is_cropped' => (bool) $media->getCustomProperty('is_cropped', false),
            'is_variant' => (bool) $media->getCustomProperty('is_variant', false),
            'parent_media_id' => $media->getCustomProperty('parent_media_id', null),
            'is_trashed' => $media->trashed(),
            'deleted_at' => $media->deleted_at ? $media->deleted_at->format('M d, Y H:i') : null,
            'created_at' => $media->created_at->format('M d, Y H:i'),
            'is_accounting_protected' => $isAccountingProtected,
            'accounting_bill_number' => $media->getCustomProperty('bill_number', ''),
            'accounting_invoice_number' => $media->getCustomProperty('invoice_number', ''),
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }
}
