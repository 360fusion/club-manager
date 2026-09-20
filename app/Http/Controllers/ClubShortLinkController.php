<?php

namespace App\Http\Controllers;

use App\Models\Club;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * `/{club}` is a short link that is safe to share: members land in the member
 * area for that club, everyone else on the public website.
 */
class ClubShortLinkController extends Controller
{
    public function __invoke(Request $request, string $slug): RedirectResponse
    {
        $club = Club::where('slug', $slug)->firstOrFail();
        $user = $request->user();

        $isMember = $user && ($user->is_super_admin || $user->clubs()->where('clubs.id', $club->id)->exists());

        return redirect()->route($isMember ? 'member.dashboard' : 'public.site', $isMember ? ['slug' => $club->slug] : ['clubSlug' => $club->slug]);
    }
}
