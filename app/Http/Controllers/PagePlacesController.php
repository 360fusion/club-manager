<?php

namespace App\Http\Controllers;

use App\Http\Requests\PageAdmin\GeocodeRequest;
use App\Http\Requests\PageAdmin\UploadDownloadRequest;
use App\Models\MasonicHall;
use App\Services\Geocoder;
use App\Support\UploadRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Website builder helpers for places, geocoding and downloadable files. Split out of PageAdminController; the URLs, route names and permissions are unchanged.
 */
class PagePlacesController extends Controller
{
    /**
     * Turn an address typed into a Map block into candidate map positions (OpenStreetMap Nominatim).
     */
    public function geocode(GeocodeRequest $request, string $clubSlug, Geocoder $geocoder): JsonResponse
    {
        $this->findClub($clubSlug);

        $validated = $request->validated();

        $search = $geocoder->search($validated['q']);

        if ($search === null) {
            return response()->json(['found' => false, 'results' => [], 'message' => 'The map search is not available right now. Click the map to place the pin instead.'], 503);
        }

        return response()->json(['found' => $search['results'] !== []] + $search);
    }

    /**
     * Masonic halls from the directory matching what is being typed into a Map block's address, so a lodge
     * can pick its own hall without any outside lookup.
     */
    public function placeSuggestions(Request $request, string $clubSlug): JsonResponse
    {
        $this->findClub($clubSlug);

        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 3 || mb_strlen($term) > 100) {
            return response()->json(['places' => []]);
        }

        $like = '%'.addcslashes($term, '\\%_').'%';

        $places = MasonicHall::query()
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('town', 'like', $like)->orWhere('postcode', 'like', $like)->orWhere('address_line_1', 'like', $like))
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (MasonicHall $hall) => [
                'id' => $hall->id,
                'name' => $hall->name,
                'address' => $hall->fullAddress(),
                'label' => $hall->name.($hall->town ? ' — '.$hall->town : '').($hall->postcode ? ', '.$hall->postcode : ''),
            ])
            ->values();

        return response()->json(['places' => $places]);
    }

    /**
     * Store a file for a Downloads block on the private disk: it is only ever served by the site's own
     * download route, which applies the block's members-only setting.
     */
    public function uploadDownload(UploadDownloadRequest $request, string $clubSlug): JsonResponse
    {
        $club = $this->findClub($clubSlug);

        $file = $request->file('file');

        if ($unsafe = UploadRules::assertSafeUpload($file)) {
            return response()->json(['success' => false, 'message' => $unsafe], 422);
        }

        if (! $club->hasStorageFor((int) $file->getSize())) {
            return response()->json(['success' => false, 'message' => 'Storage quota exceeded. Free up space in the file manager first.'], 422);
        }

        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $media = $club->addMediaFromRequest('file')
            ->usingName($baseName)
            ->toMediaCollection('page_downloads', 'local');

        return response()->json([
            'success' => true,
            'media_id' => $media->id,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => (int) $media->size,
            'title' => trim((string) preg_replace('/[_\-]+/', ' ', $baseName)),
            'added_at' => now()->toDateString(),
        ]);
    }
}
