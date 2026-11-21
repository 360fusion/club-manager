<?php

namespace App\Http\Controllers;

use App\Models\PageTiming;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives the page timings a signed-in member's device sends in small batches (Support/pageTimings.js).
 */
class PageTimingController extends Controller
{
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'timings' => ['required', 'array', 'max:20'],
            'timings.*.page' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z][A-Za-z0-9\/]*$/'],
            'timings.*.kind' => ['required', 'in:'.PageTiming::LOAD.','.PageTiming::VISIT],
            'timings.*.ms' => ['required', 'integer', 'min:0', 'max:120000'],
            'timings.*.server_ms' => ['nullable', 'integer', 'min:0', 'max:120000'],
            'timings.*.cached' => ['boolean'],
            'timings.*.device' => ['required', 'in:phone,desktop'],
            'timings.*.installed' => ['boolean'],
        ]);

        foreach ($validated['timings'] as $timing) {
            PageTiming::create([
                'page' => $timing['page'],
                'kind' => $timing['kind'],
                'ms' => $timing['ms'],
                'server_ms' => $timing['server_ms'] ?? null,
                'cached' => (bool) ($timing['cached'] ?? false),
                'device' => $timing['device'],
                'installed' => (bool) ($timing['installed'] ?? false),
            ]);
        }

        return response()->noContent();
    }
}
