<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\MembershipPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MembershipAdminController extends Controller
{
    /**
     * Display a listing of club membership plans.
     */
    public function index(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $plans = MembershipPlan::where('club_id', $club->id)
            ->withCount('memberships')
            ->orderBy('price')
            ->get();

        return Inertia::render('Admin/Memberships/Index', [
            'club' => $club,
            'plans' => $plans,
        ]);
    }

    /**
     * Show form for creating or editing a membership plan.
     */
    public function edit(string $clubSlug, ?int $id = null): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $plan = $id
            ? MembershipPlan::where('club_id', $club->id)->findOrFail($id)
            : new MembershipPlan([
                'club_id' => $club->id,
                'price' => 25.00,
                'billing_period' => 'monthly',
            ]);

        return Inertia::render('Admin/Memberships/Form', [
            'club' => $club,
            'plan' => $plan,
        ]);
    }

    /**
     * Store or update a membership plan.
     */
    public function store(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'billing_period' => 'required|in:monthly,yearly,one_time',
        ]);

        MembershipPlan::updateOrCreate(
            ['id' => $validated['id'] ?? null, 'club_id' => $club->id],
            [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? '',
                'price' => $validated['price'],
                'billing_period' => $validated['billing_period'],
            ]
        );

        return redirect()->route('admin.memberships.index', ['clubSlug' => $club->slug])
            ->with('success', 'Membership plan saved successfully.');
    }

    /**
     * Delete a membership plan.
     */
    public function destroy(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $plan = MembershipPlan::where('club_id', $club->id)->findOrFail($id);
        $plan->delete();

        return redirect()->back()->with('success', 'Membership plan deleted successfully.');
    }
}
