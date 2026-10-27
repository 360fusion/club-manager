<?php

namespace App\Http\Controllers;

use App\Models\Lodge;
use App\Support\ClubAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The managing lodge's secretary (or an owner or admin) confirms the directory listing is right, which the public
 * page shows as the date it was last checked.
 */
class LodgeListingCheckController extends Controller
{
    public function __invoke(Request $request, string $slug): RedirectResponse
    {
        $lodge = Lodge::where('slug', $slug)->whereNotNull('club_id')->with('club')->firstOrFail();

        ClubAccess::authorize($request->user(), $lodge->club, 'manage_meetings');

        $lodge->update(['last_verified_at' => now()]);

        return back()->with('success', 'Thank you: the listing now shows it was checked today.');
    }
}
