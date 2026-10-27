<?php

namespace App\Http\Controllers;

use App\Http\Requests\PageAdmin\StoreRedirectRequest;
use App\Models\ClubRedirect;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Redirects from old page addresses. Split out of PageAdminController; the URLs, route names and permissions are unchanged.
 */
class PageRedirectController extends Controller
{
    /**
     * Send an address on the club's site (after /site/{club}/) somewhere else, for example from the old website.
     * A page that exists at the address always wins, so a redirect can never hide a live page.
     */
    public function storeRedirect(StoreRedirectRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        $from = ClubRedirect::normalisePath($validated['from_path']);
        $to = trim($validated['to_url']);

        $sitePath = '/site/'.$club->slug.$from;

        if ($from === '/' || ClubRedirect::normalisePath($to) === $sitePath || ClubRedirect::normalisePath($to) === $from) {
            throw ValidationException::withMessages(['from_path' => 'Choose an old address that is different from where it should go.']);
        }

        if (Page::where('club_id', $club->id)->where('is_published', true)->where('slug', ltrim($from, '/'))->exists()) {
            throw ValidationException::withMessages(['from_path' => 'A published page already lives at that address. Unpublish or rename the page first.']);
        }

        if (! ClubRedirect::where('club_id', $club->id)->where('from_path', $from)->exists()
            && ClubRedirect::where('club_id', $club->id)->count() >= ClubRedirect::MAX_PER_CLUB) {
            throw ValidationException::withMessages(['from_path' => 'A site can have up to '.ClubRedirect::MAX_PER_CLUB.' redirects. Remove some you no longer need.']);
        }

        ClubRedirect::updateOrCreate(
            ['club_id' => $club->id, 'from_path' => $from],
            ['to_url' => $to, 'is_permanent' => $validated['is_permanent'] ?? true],
        );

        return redirect()->back()->with('success', 'Redirect saved.');
    }

    public function destroyRedirect(string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        ClubRedirect::where('club_id', $club->id)->findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Redirect removed.');
    }
}
