<?php

namespace App\Http\Controllers;

use App\Models\Club;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sends old `/clubs/{club}/...` URLs to their new top-level home, so emailed
 * links and bookmarks keep working.
 *
 * The member area used to live under `/portal`, which is now the club root:
 * `/clubs/x/portal` -> `/x`, `/clubs/x/portal/events` -> `/x/events`.
 */
class LegacyClubUrlController extends Controller
{
    public function __invoke(Request $request, string $legacySlug, ?string $path = null): RedirectResponse
    {
        $slug = $legacySlug === 'oxford-boating'
            ? (Club::first()?->slug ?? 'lodge-of-fraternity')
            : $legacySlug;

        $path = trim((string) $path, '/');

        if ($path === 'portal') {
            $path = '';
        } elseif (str_starts_with($path, 'portal/')) {
            $path = substr($path, strlen('portal/'));
        }

        $target = '/'.$slug.($path !== '' ? '/'.$path : '');

        if ($request->getQueryString() !== null) {
            $target .= '?'.$request->getQueryString();
        }

        // 308 keeps the method and body for form posts; GET links get a normal 301.
        return redirect($target, $request->isMethodSafe() ? 301 : 308);
    }
}
