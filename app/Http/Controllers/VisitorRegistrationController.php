<?php

namespace App\Http\Controllers;

use App\Http\Requests\VisitorRegistration\StoreVisitorRegistrationRequest;
use App\Models\Club;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
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
        $club = $this->findClub($clubSlug);

        return Inertia::render('Public/VisitorRegister', [
            'club' => $club,
        ]);
    }

    /**
     * Process visitor sign-up for a club.
     */
    public function store(StoreVisitorRegistrationRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        // Find or create user account
        $user = User::where('email', strtolower($validated['email']))->first();

        if (! $user) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
                'password' => Hash::make(Str::random(24)),
            ]);
        }

        // Nobody is signed in here, so an existing membership (any role or status) is never changed.
        if (! $club->users()->where('users.id', $user->id)->exists()) {
            $club->users()->attach($user->id, [
                'role' => 'visitor',
                'rank' => $validated['rank'] ?? null,
                'home_club_name' => $validated['home_club_name'],
                'home_club_number' => $validated['home_club_number'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'dietary_notes' => $validated['dietary_notes'] ?? null,
                'status' => 'active',
            ]);
        }

        return redirect()->back()->with('success', "Thank you! You have been registered as a Visitor for {$club->name}. You will receive meeting summonses and RSVP invites via email.");
    }
}
