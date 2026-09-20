<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class VisitorRegistrationController extends Controller
{
    /**
     * Display public visitor sign-up form for a club.
     */
    public function create(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        return Inertia::render('Public/VisitorRegister', [
            'club' => $club,
        ]);
    }

    /**
     * Process visitor sign-up for a club.
     */
    public function store(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'rank' => 'nullable|string|max:100',
            'home_club_name' => 'required|string|max:255',
            'home_club_number' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'dietary_notes' => 'nullable|string',
        ]);

        // Find or create user account
        $user = User::where('email', strtolower($validated['email']))->first();

        if (! $user) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
                'password' => Hash::make(Str::random(24)),
            ]);
        } else {
            // Update name if needed
            $user->update(['name' => $validated['name']]);
        }

        // Attach or update visitor relationship in club_user pivot
        $club->users()->syncWithoutDetaching([
            $user->id => [
                'role' => 'visitor',
                'rank' => $validated['rank'] ?? null,
                'home_club_name' => $validated['home_club_name'],
                'home_club_number' => $validated['home_club_number'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'dietary_notes' => $validated['dietary_notes'] ?? null,
                'status' => 'active',
            ],
        ]);

        return redirect()->back()->with('success', "Thank you! You have been registered as a Visitor for {$club->name}. You will receive meeting summonses and RSVP invites via email.");
    }
}
