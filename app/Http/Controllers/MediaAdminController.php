<?php

namespace App\Http\Controllers;

use App\Models\Accounting\Bill;
use App\Models\Club;
use App\Models\Invoice;
use App\Models\Media;
use App\Models\MediaVersion;
use App\Models\Post;
use App\Support\ClubAccess;
use App\Support\ImageDownscaler;
use App\Support\MediaFolders;
use App\Support\UploadRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        $canViewAccounting = ClubAccess::can(auth()->user(), $club, 'manage_billing');

        if ($folder === 'accounting' && ! $canViewAccounting) {
            abort(403, 'Your role does not have access to the Accounting area.');
        }

        $trashCount = $club->media()->onlyTrashed()
            ->where('collection_name', '!=', 'page_downloads')
            ->when(! $canViewAccounting, fn ($q) => $q->where('collection_name', '!=', 'accounting'))
            ->count();

        // Fetch all non-trashed club media once to build metadata filters (available extensions & dates)
        $allClubMedia = $club->media()
            ->where('collection_name', '!=', 'page_downloads')
            ->when(! $canViewAccounting, fn ($q) => $q->where('collection_name', '!=', 'accounting'))
            ->get();

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

        // Private downloads for website pages are served by the site itself, never listed in the file manager.
        $query->where('collection_name', '!=', 'page_downloads');

        if (! $canViewAccounting) {
            $query->where('collection_name', '!=', 'accounting');
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

        $mediaItems = $query->withCount('versions')->get()->map(function ($media) {
            return $this->transformMedia($media);
        });

        return response()->json([
            'media' => $mediaItems,
            'trash_count' => $trashCount,
            'available_extensions' => $availableExtensions,
            'available_months' => $availableMonths,
            'storage' => $club->storageSummary(),
            'custom_folders' => $club->mediaFolders()->orderBy('name')->get(['id', 'name', 'slug', 'parent_slug']),
        ]);
    }

    /**
     * Create a club-defined folder. Its slug becomes the media collection_name once
     * files are uploaded into it, so every existing folder/listing code works unchanged.
     */
    public function storeFolder(string $clubSlug, Request $request): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $request->validate([
            'name' => 'required|string|max:60',
            'parent' => ['required', 'string', Rule::in(MediaFolders::NESTABLE)],
        ]);

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
    public function updateFolder(string $clubSlug, int $folderId, Request $request): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $folder = $club->mediaFolders()->findOrFail($folderId);

        $request->validate([
            'name' => 'required|string|max:60',
        ]);

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
        $club = Club::where('slug', $clubSlug)->firstOrFail();
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

    /**
     * The set of folder identifiers a file may be uploaded/moved into: the fixed
     * system folders plus this club's own custom folders.
     */
    private function folderRule(Club $club): In
    {
        return Rule::in([...MediaFolders::SYSTEM, ...$club->mediaFolders()->pluck('slug')->all()]);
    }

    /**
     * Upload a new file directly into the specified club media collection / folder.
     */
    public function store(string $clubSlug, Request $request): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $folder = $request->input('folder', 'images');

        // Enforce folder-specific rules: Image-only folders vs General folders
        $allowedRule = in_array($folder, MediaFolders::IMAGE_ONLY, true) ? UploadRules::IMAGE_TYPES : UploadRules::IMAGE_TYPES.','.UploadRules::DOCUMENT_TYPES;

        $request->validate([
            'folder' => ['required', 'string', $this->folderRule($club)],
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
            'file.mimes' => in_array($folder, MediaFolders::IMAGE_ONLY, true)
                ? 'Invalid file format for this folder. Only image files (JPG, PNG, GIF, WEBP) are allowed.'
                : 'Invalid file format. Allowed file types: JPG, PNG, GIF, WEBP, PDF, DOC, DOCX, XLS, XLSX, CSV, PPT, PPTX, TXT, RTF, ZIP.',
        ]);

        $uploadedFile = $request->file('file');
        $originalName = $uploadedFile->getClientOriginalName();

        if ($error = UploadRules::assertSafeUpload($uploadedFile)) {
            return response()->json([
                'message' => 'Security check failed: '.$error,
                'errors' => ['file' => [$error]],
            ], 422);
        }

        if ($quotaError = $this->assertQuotaAvailable($club, $uploadedFile->getSize())) {
            return $quotaError;
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

        if ($this->isAccountingProtected($media)) {
            return response()->json([
                'success' => false,
                'message' => "Protected Audit Document: '{$media->file_name}' is attached to an Accounting bill or invoice. It cannot be edited from the file manager; manage or delete it directly from the Accounting page.",
            ], 422);
        }

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
     * - 'replace': Overwrites the current asset in place, keeping its id, with the prior
     *   image saved to version history.
     */
    public function crop(string $clubSlug, int $id, Request $request): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $media = $club->media()->findOrFail($id);

        if ($this->isAccountingProtected($media)) {
            return response()->json([
                'success' => false,
                'message' => "Protected Audit Document: '{$media->file_name}' is attached to an Accounting bill or invoice. It cannot be edited from the file manager; manage or delete it directly from the Accounting page.",
            ], 422);
        }

        $request->validate([
            'file' => UploadRules::image(10240, required: true),
            'save_mode' => 'nullable|string|in:replace,variant',
        ]);

        $file = $request->file('file');
        $saveMode = $request->input('save_mode', 'replace');
        $collection = $media->collection_name;

        if ($quotaError = $this->assertQuotaAvailable($club, $file->getSize())) {
            return $quotaError;
        }

        // Downscale large camera photos > 1920px max dimension before storing, same as store()/replaceFile().
        ImageDownscaler::apply($file);

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

        $updatedMedia = $this->replaceMediaFile($media, $file, 'Cropped', auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'Image updated successfully. Previous version saved to history.',
            'media' => $this->transformMedia($updatedMedia),
        ]);
    }

    /**
     * Upload a replacement file for an existing media item, of any type — the current
     * content is saved to version history before being overwritten in place.
     */
    public function replaceFile(string $clubSlug, int $id, Request $request): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $media = $club->media()->findOrFail($id);

        if ($this->isAccountingProtected($media)) {
            return response()->json([
                'success' => false,
                'message' => "Protected Audit Document: '{$media->file_name}' is attached to an Accounting bill or invoice. It cannot be replaced from the file manager; manage or delete it directly from the Accounting page.",
            ], 422);
        }

        $allowedRule = in_array($media->collection_name, MediaFolders::IMAGE_ONLY, true) ? UploadRules::IMAGE_TYPES : UploadRules::IMAGE_TYPES.','.UploadRules::DOCUMENT_TYPES;

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240',
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
        ]);

        $file = $request->file('file');

        if ($error = UploadRules::assertSafeUpload($file)) {
            return response()->json([
                'message' => 'Security check failed: '.$error,
                'errors' => ['file' => [$error]],
            ], 422);
        }

        if ($quotaError = $this->assertQuotaAvailable($club, $file->getSize())) {
            return $quotaError;
        }

        if (str_starts_with((string) $file->getMimeType(), 'image/')) {
            ImageDownscaler::apply($file);
        }

        $updatedMedia = $this->replaceMediaFile($media, $file, 'Replaced file', auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'File replaced. Previous version saved to history.',
            'media' => $this->transformMedia($updatedMedia),
        ]);
    }

    /**
     * List the stored version history of a media item, newest first.
     */
    public function versions(string $clubSlug, int $id): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
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
        $club = Club::where('slug', $clubSlug)->firstOrFail();
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

        $this->snapshotVersion($media, 'Before restoring version from '.$version->created_at->format('M d, Y H:i'), auth()->id());

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
        $club = Club::where('slug', $clubSlug)->firstOrFail();
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
        $club = Club::where('slug', $clubSlug)->firstOrFail();
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
            'human_size' => Club::formatStorageBytes($media->size),
            'collection_name' => $media->collection_name,
            'original_url' => $media->disk === 'public' ? $media->getFullUrl() : $this->privateUrl($media),
            'alt_text' => $media->getCustomProperty('alt_text', ''),
            'caption' => $media->getCustomProperty('caption', ''),
            'version_count' => $media->versions_count ?? $media->versions()->count(),
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

    private function transformVersion(Media $media, MediaVersion $version): array
    {
        return [
            'id' => $version->id,
            'file_name' => $version->file_name,
            'mime_type' => $version->mime_type,
            'human_size' => Club::formatStorageBytes($version->size),
            'note' => $version->note,
            'uploaded_by' => $version->uploader?->name,
            'created_at' => $version->created_at->format('M d, Y H:i'),
            'download_url' => route('admin.media.versions.download', [
                'clubSlug' => Club::whereKey($media->model_id)->value('slug'),
                'id' => $media->id,
                'versionId' => $version->id,
            ]),
        ];
    }

    /**
     * Copy a media item's current file content into version history before it is overwritten.
     */
    private function snapshotVersion(Media $media, ?string $note, ?int $userId): MediaVersion
    {
        $versionDisk = Storage::disk('local');
        $versionDir = "media-versions/{$media->model_id}/{$media->id}";
        $versionDisk->makeDirectory($versionDir);

        $extension = pathinfo($media->file_name, PATHINFO_EXTENSION);
        $versionFileName = now()->format('YmdHis').'_'.Str::random(8).($extension ? ".{$extension}" : '');
        $versionPath = "{$versionDir}/{$versionFileName}";

        $versionDisk->put($versionPath, Storage::disk($media->disk)->get($media->getPathRelativeToRoot()));

        $version = MediaVersion::create([
            'media_id' => $media->id,
            'club_id' => $media->model_id,
            'disk' => 'local',
            'path' => $versionPath,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'note' => $note,
            'created_by' => $userId,
        ]);

        // Only the newest few versions are kept, so repeated edits cannot fill the disk.
        $media->pruneVersions();

        return $version;
    }

    /**
     * Overwrite a media item's file content in place, keeping its id, after saving
     * the current content to version history.
     */
    private function replaceMediaFile(Media $media, UploadedFile $file, string $note, ?int $userId): Media
    {
        $this->snapshotVersion($media, $note, $userId);

        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $file->getClientOriginalName());
        $liveDisk = Storage::disk($media->disk);
        $directory = dirname($media->getPathRelativeToRoot());

        $liveDisk->deleteDirectory($directory);
        $liveDisk->makeDirectory($directory);
        $liveDisk->putFileAs($directory, $file, $safeName);

        $media->file_name = $safeName;
        $media->mime_type = $file->getMimeType();
        $media->size = $file->getSize();
        $media->save();

        return $media->fresh();
    }

    /**
     * Returns a 422 JSON response if storing `$incomingBytes` more would exceed the
     * club's storage quota, or null if there's room.
     */
    private function assertQuotaAvailable(Club $club, int $incomingBytes): ?JsonResponse
    {
        if (! $club->hasStorageFor($incomingBytes)) {
            $quota = (int) $club->storageQuotaBytes();
            $used = $club->storageUsedBytes();

            return response()->json([
                'success' => false,
                'message' => 'Storage quota exceeded. This club has used '.Club::formatStorageBytes($used).' of '.Club::formatStorageBytes($quota).' available.',
                'storage' => $club->storageSummary(),
            ], 422);
        }

        return null;
    }
}
