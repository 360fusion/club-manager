<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
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

        $user = $club->users()
            ->where('club_user.invitation_token', $token)
            ->first();

        if (! $user) {
            return redirect()->route('login')->with('error', 'Invitation link is invalid or has already been used.');
        }

        $expiryDays = (int) ($club->settings['invite_expiration_days'] ?? 14);
        if ($user->pivot->invited_at && Carbon::parse($user->pivot->invited_at)->addDays($expiryDays)->isPast()) {
            return redirect()->route('login')->with('error', "This invitation link expired after {$expiryDays} days. Please request a new invitation from your club administrator.");
        }

        // If currently logged in as the invited user, automatically activate and grant access
        if (Auth::check() && Auth::id() === $user->id) {
            $club->users()->updateExistingPivot($user->id, [
                'invitation_accepted_at' => now(),
                'invitation_token' => null,
                'status' => 'active',
            ]);

            return redirect()->route('member.dashboard', ['slug' => $club->slug])
                ->with('success', "Welcome to {$club->name}! You now have access to your new club portal.");
        }

        $isExistingUser = ! empty($user->password) && $user->clubs()->wherePivotNotNull('invitation_accepted_at')->exists();

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
            'isExistingUser' => $isExistingUser,
        ]);
    }

    /**
     * Accept invitation, set password, and log in user.
     */
    public function accept(Request $request, string $clubSlug, string $token): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $user = $club->users()
            ->where('club_user.invitation_token', $token)
            ->first();

        if (! $user) {
            return redirect()->route('login')->with('error', 'Invitation link is invalid or has already been used.');
        }

        $expiryDays = (int) ($club->settings['invite_expiration_days'] ?? 14);
        if ($user->pivot->invited_at && Carbon::parse($user->pivot->invited_at)->addDays($expiryDays)->isPast()) {
            return redirect()->route('login')->with('error', "This invitation link expired after {$expiryDays} days. Please request a new invitation from your club administrator.");
        }

        $isExistingUser = ! empty($user->password) && $user->clubs()->wherePivotNotNull('invitation_accepted_at')->exists();

        if ($isExistingUser) {
            $request->validate([
                'password' => 'required|string',
            ]);

            if (! Hash::check($request->password, $user->password)) {
                return back()->withErrors(['password' => 'Incorrect password for your existing account.']);
            }
        } else {
            $request->validate([
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);

            $user->password = Hash::make($request->password);
            $user->save();
        }

        $club->users()->updateExistingPivot($user->id, [
            'invitation_accepted_at' => now(),
            'invitation_token' => null,
            'status' => 'active',
        ]);

        Auth::login($user);

        return redirect()->route('member.dashboard', ['slug' => $club->slug])
            ->with('success', "Welcome to {$club->name}! Your access has been confirmed.");
    }
}
