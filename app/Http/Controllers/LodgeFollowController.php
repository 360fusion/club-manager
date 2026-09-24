<?php

namespace App\Http\Controllers;

use App\Models\Lodge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Following a lodge from the public directory. A follow is private to the member.
 */
class LodgeFollowController extends Controller
{
    /**
     * More than this is a sign of a script, not a person.
     */
    public const MAX_FOLLOWS = 100;

    public function store(Request $request, string $slug): RedirectResponse
    {
        $lodge = Lodge::listed()->where('slug', $slug)->firstOrFail();
        $user = $request->user();

        if (! $user->followedLodges()->whereKey($lodge->id)->exists()) {
            if ($user->followedLodges()->count() >= self::MAX_FOLLOWS) {
                throw ValidationException::withMessages(['follow' => 'You can follow up to '.self::MAX_FOLLOWS.' lodges. Unfollow one first.']);
            }

            $user->followedLodges()->attach($lodge->id, ['in_calendar' => true]);
        }

        return back()->with('success', 'You are following '.$lodge->displayName().'. Its expected meetings are in your calendar.');
    }

    public function destroy(Request $request, string $slug): RedirectResponse
    {
        $lodge = Lodge::where('slug', $slug)->firstOrFail();

        $request->user()->followedLodges()->detach($lodge->id);

        return back()->with('success', 'You no longer follow '.$lodge->displayName().'.');
    }

    /**
     * Change a follow: put the lodge's expected meetings in, or take them out of, the calendar, or choose to hear about its summonses.
     */
    public function update(Request $request, string $slug): RedirectResponse
    {
        $validated = $request->validate(['in_calendar' => 'sometimes|required|boolean', 'notify_summons' => 'sometimes|required|boolean']);
        $lodge = Lodge::where('slug', $slug)->firstOrFail();

        abort_unless($request->user()->followedLodges()->whereKey($lodge->id)->exists(), 404);

        if ($validated !== []) {
            $request->user()->followedLodges()->updateExistingPivot($lodge->id, $validated);
        }

        return back()->with('success', match (true) {
            array_key_exists('notify_summons', $validated) => $validated['notify_summons'] ? 'We will tell you when they publish a summons.' : 'You will not be told about their summonses.',
            default => ($validated['in_calendar'] ?? true) ? 'Added to your calendar.' : 'Removed from your calendar.',
        });
    }
}
