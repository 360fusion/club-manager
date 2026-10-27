<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MediaUploadChecks;
use App\Http\Controllers\Concerns\TransformsMedia;
use App\Http\Requests\MediaAdmin\CropRequest;
use App\Http\Requests\MediaAdmin\UpdateMediaRequest;
use App\Models\Club;
use App\Models\Media;
use App\Services\MediaVersions;
use App\Support\ClubAccess;
use App\Support\ImageDownscaler;
use App\Support\MediaFolders;
use App\Support\UploadRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\In;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class MediaAdminController extends Controller
{
    use MediaUploadChecks;
    use TransformsMedia;

    /** How many files the file manager loads at a time. */
    private const PAGE_SIZE = 60;

    public function __construct(protected MediaVersions $versions) {}

    /**
     * Render full page File Manager view.
     */
    public function page(string $clubSlug): InertiaResponse
    {
        $club = $this->findClub($clubSlug);

        return Inertia::render('Admin/Media/Index', [
            'club' => $club,
        ]);
    }

    /**
     * List media items for a club, filtered by folder collection, search query, type, extension, date, and sort order.
     */
    public function index(string $clubSlug, Request $request): JsonResponse
    {
        $club = $this->findClub($clubSlug);
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

        // Only two columns of every file are needed to offer the extension and month filters.
        $allClubMedia = $club->media()
            ->where('collection_name', '!=', 'page_downloads')
            ->when(! $canViewAccounting, fn ($q) => $q->where('collection_name', '!=', 'accounting'))
            ->get(['id', 'model_id', 'model_type', 'file_name', 'created_at']);

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

        $query->withCount('versions');

        // The file manager asks for a page at a time (`page`); callers that do not, such as the picker, get every file.
        if ($request->has('page')) {
            $paged = $query->paginate(self::PAGE_SIZE, ['*'], 'page', max(1, (int) $request->query('page')));
            $mediaItems = $paged->getCollection()->map(fn ($media) => $this->transformMedia($media));
            $paging = ['has_more' => $paged->hasMorePages(), 'total' => $paged->total()];
        } else {
            $mediaItems = $query->get()->map(fn ($media) => $this->transformMedia($media));
            $paging = ['has_more' => false, 'total' => $mediaItems->count()];
        }

        return response()->json([
            'media' => $mediaItems,
            ...$paging,
            'trash_count' => $trashCount,
            'available_extensions' => $availableExtensions,
            'available_months' => $availableMonths,
            'storage' => $club->storageSummary(),
            'custom_folders' => $club->mediaFolders()->orderBy('name')->get(['id', 'name', 'slug', 'parent_slug']),
        ]);
    }

    /**
     * Upload a new file directly into the specified club media collection / folder.
     */
    public function store(string $clubSlug, Request $request): JsonResponse
    {
        $club = $this->findClub($clubSlug);

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
    public function update(string $clubSlug, int $id, UpdateMediaRequest $request): JsonResponse
    {
        $club = $this->findClub($clubSlug);
        $media = $club->media()->findOrFail($id);

        if ($this->isAccountingProtected($media)) {
            return response()->json([
                'success' => false,
                'message' => "Protected Audit Document: '{$media->file_name}' is attached to an Accounting bill or invoice. It cannot be edited from the file manager; manage or delete it directly from the Accounting page.",
            ], 422);
        }

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
    public function crop(string $clubSlug, int $id, CropRequest $request): JsonResponse
    {
        $club = $this->findClub($clubSlug);
        $media = $club->media()->findOrFail($id);

        if ($this->isAccountingProtected($media)) {
            return response()->json([
                'success' => false,
                'message' => "Protected Audit Document: '{$media->file_name}' is attached to an Accounting bill or invoice. It cannot be edited from the file manager; manage or delete it directly from the Accounting page.",
            ], 422);
        }

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

        $updatedMedia = $this->versions->replace($media, $file, 'Cropped', auth()->id());

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
        $club = $this->findClub($clubSlug);
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

        $updatedMedia = $this->versions->replace($media, $file, 'Replaced file', auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'File replaced. Previous version saved to history.',
            'media' => $this->transformMedia($updatedMedia),
        ]);
    }

    /**
     * Soft delete a media item (move to Trash bin).
     */
    public function destroy(string $clubSlug, int $id): JsonResponse
    {
        $club = $this->findClub($clubSlug);
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

    private function formatTitleFromFilename(string $filename): string
    {
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        $clean = preg_replace('/[_\-\.]+/', ' ', $nameWithoutExt);

        return ucwords(trim($clean));
    }
}
