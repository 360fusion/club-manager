<?php

namespace App\Http\Controllers;

use App\Models\Lodge;
use App\Models\LodgeClaim;
use App\Services\Lodges\LodgeClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Asking to manage a lodge's listing, and following up on the request. Every change goes through
 * LodgeClaimService.
 */
class LodgeClaimController extends Controller
{
    public function __construct(private readonly LodgeClaimService $claims) {}

    public function create(Request $request, string $slug): Response|RedirectResponse
    {
        $lodge = Lodge::listed()->with(['clubType:id,name', 'province:id,name', 'masonicHall:id,name,town'])->where('slug', $slug)->firstOrFail();

        if ($lodge->isManaged()) {
            return redirect()->route('lodges.show', $lodge->slug)->with('error', 'This lodge is already managed. Ask its secretary for access.');
        }

        if ($lodge->claims()->where('user_id', $request->user()->id)->open()->exists()) {
            return redirect()->route('members.lodges')->with('success', 'You already have a claim for this lodge waiting to be checked.');
        }

        return Inertia::render('Lodges/Claim', [
            'lodge' => [
                'slug' => $lodge->slug,
                'name' => $lodge->displayName(),
                'number' => $lodge->number,
                'order' => $lodge->clubType?->name,
                'province' => $lodge->province?->name,
                'hall' => $lodge->masonicHall?->name,
            ],
            'roles' => LodgeClaim::ROLES,
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $lodge = Lodge::listed()->where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'claimant_role' => ['required', Rule::in(array_keys(LodgeClaim::ROLES))],
            'message' => 'required|string|min:10|max:2000',
            'phone' => 'nullable|string|max:50',
            'evidence' => 'nullable|string|max:2000',
            'confirm' => 'accepted',
        ]);

        $this->claims->submit($lodge, $request->user(), collect($validated)->only(['claimant_role', 'message', 'phone', 'evidence'])->all());

        return redirect()->route('members.lodges')->with('success', 'Your claim has been sent. A member of our team will check it and be in touch.');
    }

    public function respond(Request $request, int $id): RedirectResponse
    {
        $claim = LodgeClaim::findOrFail($id);
        $validated = $request->validate(['reply' => 'required|string|min:2|max:2000']);

        $this->claims->respond($claim, $request->user(), $validated['reply']);

        return back()->with('success', 'Thank you. Your reply has been sent.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->claims->withdraw(LodgeClaim::findOrFail($id), $request->user());

        return back()->with('success', 'Your claim has been withdrawn.');
    }
}
