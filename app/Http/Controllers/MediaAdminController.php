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
