<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\User;
use App\Support\EmailVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $email = strtolower($request->email);

        $clubSlug = $request->input('club') ?? $request->input('club_slug');
        $club = $clubSlug ? Club::where('slug', $clubSlug)->first() : null;

        if ($club && ($club->settings['registration_mode'] ?? 'open') === 'invite_only') {
            return back()->withInput($request->only('name', 'email'))->withErrors(['email' => 'This club only accepts members by invitation. Please ask the club secretary to invite you.']);
        }

        // The response is the same whether or not the address already has an
        // account, so this form cannot be used to find out who is registered.
        if (! User::where('email', $email)->exists()) {
            $user = User::create([
                'name' => $request->name,
                'email' => $email,
                'password' => Hash::make($request->password),
            ]);

            if ($club) {
                $user->clubs()->attach($club->id, [
                    'role' => 'member',
                    'member_number' => 'MEM-'.strtoupper(substr(uniqid(), -5)),
                    'status' => 'pending',
                ]);
            }

            if (EmailVerification::required()) {
                $user->sendEmailVerificationNotification();
            } else {
                $user->markEmailAsVerified();
            }
        }

        $message = EmailVerification::required()
            ? 'Check your email for a link to confirm your address, then sign in.'
            : 'Your account is ready, you can now sign in.';

        return redirect()->route('login')->with('success', $message.' If you already have an account, use the login page or reset your password.');
    }
}
