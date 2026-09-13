<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    /**
     * Show account activation & password creation form for invited member.
     */
    public function showForm(string $clubSlug, string $token): Response|RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $user = User::whereHas('clubs', function ($query) use ($club, $token) {
            $query->where('clubs.id', $club->id)
                  ->where('club_user.invitation_token', $token);
        })->first();

        if (! $user) {
            return redirect()->route('login')->with('error', 'Invitation link is invalid or has already been used.');
        }

        return Inertia::render('Auth/AcceptInvitation', [
            'club' => [
                'name' => $club->name,
                'slug' => $club->slug,
                'logo_url' => $club->logo_url,
                'tagline' => $club->settings['tagline'] ?? '',
                'primary_color' => $club->settings['primary_color'] ?? '#0369a1',
            ],
            'token' => $token,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Accept invitation, set password, and log in user.
     */
    public function accept(Request $request, string $clubSlug, string $token): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $user = User::whereHas('clubs', function ($query) use ($club, $token) {
            $query->where('clubs.id', $club->id)
                  ->where('club_user.invitation_token', $token);
        })->first();

        if (! $user) {
            return redirect()->route('login')->with('error', 'Invitation link is invalid or has already been used.');
        }

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->password = Hash::make($request->password);
        $user->save();

        $club->users()->updateExistingPivot($user->id, [
            'invitation_accepted_at' => now(),
            'invitation_token' => null,
            'status' => 'active',
        ]);

        Auth::login($user);

        return redirect()->route('member.dashboard', ['slug' => $club->slug])
            ->with('success', "Welcome to {$club->name}! Your account has been activated.");
    }
}
