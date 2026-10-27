<?php

namespace App\Http\Controllers;

use App\Http\Requests\LodgeVisitor\StoreLodgeVisitorRequest;
use App\Models\ClubVisitorAccess;
use App\Models\Lodge;
use App\Services\Lodges\VisitorAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A visitor asking a managed lodge to share its summonses with them.
 */
class LodgeVisitorController extends Controller
{
    public function __construct(private readonly VisitorAccessService $access) {}

    public function store(StoreLodgeVisitorRequest $request, string $slug): RedirectResponse
    {
        $lodge = Lodge::listed()->with('club')->where('slug', $slug)->firstOrFail();
        abort_if($lodge->club === null, 404);

        $validated = $request->validated();

        $this->access->request($lodge->club, $request->user(), $validated);

        return back()->with('success', 'Your request has been sent to the lodge. You will be told when they answer.');
    }

    public function destroy(Request $request, string $slug): RedirectResponse
    {
        $lodge = Lodge::where('slug', $slug)->whereNotNull('club_id')->firstOrFail();

        $access = ClubVisitorAccess::where('club_id', $lodge->club_id)->where('user_id', $request->user()->id)->firstOrFail();
        $this->access->withdraw($access);

        return back()->with('success', 'Your request has been withdrawn.');
    }
}
