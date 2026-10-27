<?php

namespace App\Http\Controllers;

use App\Http\Requests\SuperAdmin\StoreGrandLodgeRequest;
use App\Http\Requests\SuperAdminGrandLodge\UpdateGrandLodgeRanksRequest;
use App\Http\Requests\SuperAdminGrandLodge\UpdateGrandLodgeRequest;
use App\Models\GrandLodge;
use App\Support\MasonicRanks;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Grand lodges and their ranks. Split out of SuperAdminController; the URLs, route names and permissions are unchanged.
 */
class SuperAdminGrandLodgeController extends Controller
{
    /**
     * List and manage Grand Lodges / Governing Bodies.
     */
    public function grandLodgesIndex(): Response
    {
        $grandLodges = GrandLodge::withCount(['provinces', 'clubs'])->get();

        return Inertia::render('SuperAdmin/GrandLodges/Index', [
            'grandLodges' => $grandLodges,
        ]);
    }

    /**
     * Store a new Grand Lodge.
     */
    public function storeGrandLodge(StoreGrandLodgeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $grandLodge = GrandLodge::create($validated);

        return redirect()->back()->with('success', "Grand Lodge '{$grandLodge->name}' created successfully.");
    }

    /**
     * Update a Grand Lodge.
     */
    public function updateGrandLodge(UpdateGrandLodgeRequest $request, int $id): RedirectResponse
    {
        $grandLodge = GrandLodge::findOrFail($id);

        $validated = $request->validated();

        $grandLodge->update($validated);

        return redirect()->back()->with('success', "Grand Lodge '{$grandLodge->name}' updated successfully.");
    }

    /**
     * The rank lists this Grand Lodge's lodges start from.
     */
    public function grandLodgeRanks(int $id): Response
    {
        $grandLodge = GrandLodge::withCount('clubs')->findOrFail($id);

        return Inertia::render('SuperAdmin/GrandLodges/Ranks', [
            'grandLodge' => $grandLodge->only(['id', 'name', 'short_name', 'clubs_count']),
            'grandRanks' => $grandLodge->grand_ranks ?? [],
            'provincialRanks' => $grandLodge->provincial_ranks ?? [],
        ]);
    }

    public function updateGrandLodgeRanks(UpdateGrandLodgeRanksRequest $request, int $id): RedirectResponse
    {
        $grandLodge = GrandLodge::findOrFail($id);

        $validated = $request->validated();

        $grandLodge->update([
            'grand_ranks' => MasonicRanks::clean($validated['grand_ranks'] ?? []),
            'provincial_ranks' => MasonicRanks::clean($validated['provincial_ranks'] ?? []),
        ]);

        return redirect()->back()->with('success', "Rank lists saved for {$grandLodge->name}. Lodges that already have their own copy are not changed; new lodges, and lodges that reset, will use these.");
    }

    /**
     * Delete a Grand Lodge.
     */
    public function destroyGrandLodge(int $id): RedirectResponse
    {
        $grandLodge = GrandLodge::findOrFail($id);
        $name = $grandLodge->name;
        $grandLodge->delete();

        return redirect()->back()->with('success', "Grand Lodge '{$name}' deleted.");
    }

    /**
     * Australian Grand Lodges reference page.
     */
    public function australianGrandLodges(): Response
    {
        $australianGrandLodges = GrandLodge::withCount(['provinces', 'clubs'])
            ->where('country', 'Australia')
            ->orderBy('name')
            ->get();

        return inertia('SuperAdmin/GrandLodges/Australia', [
            'australianGrandLodges' => $australianGrandLodges,
        ]);
    }

    /**
     * United States Grand Lodges reference page.
     */
    public function usGrandLodges(): Response
    {
        $usGrandLodges = GrandLodge::withCount(['provinces', 'clubs'])
            ->where('country', 'United States')
            ->orderBy('name')
            ->get();

        return inertia('SuperAdmin/GrandLodges/UnitedStates', [
            'usGrandLodges' => $usGrandLodges,
        ]);
    }
}
