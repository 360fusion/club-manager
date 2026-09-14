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

        $media = $club->addMediaFromRequest('file')
            ->usingName($safeName)
            ->toMediaCollection($folder);

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
