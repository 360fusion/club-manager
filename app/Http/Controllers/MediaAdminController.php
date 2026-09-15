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

        // Fetch all club media once to build metadata filters (available extensions & dates)
        $allClubMedia = $club->media()->get();

        $availableExtensions = $allClubMedia
            ->map(fn($m) => strtolower(pathinfo($m->file_name, PATHINFO_EXTENSION)))
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
        $query = $club->media();

        if ($folder && $folder !== 'all') {
            $query->where('collection_name', $folder);
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
        $imageExtensions = 'jpg,jpeg,png,gif,webp,svg';
        $docExtensions = 'pdf,doc,docx,xls,xlsx,csv,ppt,pptx,txt,rtf,zip';
        $allAllowedExtensions = "{$imageExtensions},{$docExtensions}";

        // Enforce folder-specific rules: Image-only folders vs General folders
        $imageOnlyFolders = ['logos', 'images', 'galleries'];
        $allowedRule = in_array($folder, $imageOnlyFolders) ? $imageExtensions : $allAllowedExtensions;

        $request->validate([
            'folder' => 'required|string|in:logos,news,newsletters,images,galleries,documents',
            'file' => [
                'required',
                'file',
                'max:10240', // 10MB limit (10240 KB)
                "mimes:{$allowedRule}",
            ],
        ], [
            'file.max' => 'The uploaded file exceeds the 10MB size limit.',
            'file.mimes' => in_array($folder, $imageOnlyFolders)
                ? 'Invalid file format for this folder. Only image files (JPG, PNG, GIF, WEBP, SVG) are allowed.'
                : 'Invalid file format. Allowed file types: JPG, PNG, GIF, WEBP, SVG, PDF, DOC, DOCX, XLS, XLSX, CSV, PPT, PPTX, TXT, RTF, ZIP.',
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
        $this->downscaleImageIfNeeded($uploadedFile);

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
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,gif|max:10240',
            'save_mode' => 'nullable|string|in:replace,variant',
        ]);

        $saveMode = $request->input('save_mode', 'replace');
        $collection = $media->collection_name;

        if ($saveMode === 'variant') {
            $variantName = $media->name . ' (Cropped)';
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

        if (!file_exists($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $hasBackup = $media->getCustomProperty('has_original_backup', false);
        $masterBackupPath = $media->getCustomProperty('original_master_path', null);

        if (!$hasBackup || !$masterBackupPath || !file_exists($masterBackupPath)) {
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

        if (!$masterBackupPath || !file_exists($masterBackupPath)) {
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
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        $count = 0;
        foreach ($request->input('ids') as $mediaId) {
            $media = $club->media()->find($mediaId);
            if ($media) {
                $media->delete();
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully deleted {$count} " . ($count === 1 ? 'file' : 'files') . '.',
        ]);
    }

    /**
     * Bulk move multiple media items to a target folder / collection.
     */
    public function bulkMove(string $clubSlug, Request $request): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'folder' => 'required|string|in:logos,news,newsletters,images,galleries,documents',
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
            'message' => "Successfully moved {$count} " . ($count === 1 ? 'file' : 'files') . " to '{$targetFolder}'.",
        ]);
    }

    /**
     * Check asset usage across news posts and club branding settings.
     */
    public function usage(string $clubSlug, int $id): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $media = $club->media()->findOrFail($id);

        $url = $media->getFullUrl();
        $filename = $media->file_name;
        $usages = [];

        // Check if used in Club Logo
        if ($club->logo_url && (str_contains($club->logo_url, $filename) || $club->logo_url === $url)) {
            $usages[] = [
                'type' => 'Club Logo',
                'title' => $club->name . ' Logo',
                'location' => 'Club Settings',
            ];
        }

        // Check if used in News Articles / Posts
        $posts = \App\Models\Post::where('club_id', $club->id)->get();
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

        return response()->json([
            'usage_count' => count($usages),
            'usages' => $usages,
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

    private function formatTitleFromFilename(string $filename): string
    {
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        $clean = preg_replace('/[_\-\.]+/', ' ', $nameWithoutExt);
        return ucwords(trim($clean));
    }

    private function downscaleImageIfNeeded($uploadedFile): void
    {
        $mime = $uploadedFile->getMimeType();
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) {
            return;
        }

        $path = $uploadedFile->getRealPath();
        [$width, $height] = @getimagesize($path);
        if (!$width || !$height) {
            return;
        }

        $maxDimension = 1920;
        if ($width <= $maxDimension && $height <= $maxDimension) {
            return;
        }

        if ($width >= $height) {
            $newWidth = $maxDimension;
            $newHeight = (int) round(($height / $width) * $maxDimension);
        } else {
            $newHeight = $maxDimension;
            $newWidth = (int) round(($width / $height) * $maxDimension);
        }

        $srcImage = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default      => null,
        };

        if (!$srcImage) {
            return;
        }

        $dstImage = imagecreatetruecolor($newWidth, $newHeight);
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
        }

        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        match ($mime) {
            'image/jpeg' => imagejpeg($dstImage, $path, 85),
            'image/png'  => imagepng($dstImage, $path, 8),
            'image/webp' => imagewebp($dstImage, $path, 85),
        };

        imagedestroy($srcImage);
        imagedestroy($dstImage);
    }

    private function transformMedia($media): array
    {
        return [
            'id' => $media->id,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'human_size' => $this->formatBytes($media->size),
            'collection_name' => $media->collection_name,
            'original_url' => $media->getFullUrl(),
            'alt_text' => $media->getCustomProperty('alt_text', ''),
            'caption' => $media->getCustomProperty('caption', ''),
            'has_original_backup' => (bool) ($media->getCustomProperty('has_original_backup', false) && file_exists($media->getCustomProperty('original_master_path', ''))),
            'is_cropped' => (bool) $media->getCustomProperty('is_cropped', false),
            'created_at' => $media->created_at->format('M d, Y H:i'),
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        return round($bytes / 1024, 1) . ' KB';
    }
}
