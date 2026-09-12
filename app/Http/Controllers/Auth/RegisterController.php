<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $clubSlug = $request->input('club') ?? $request->input('club_slug');
        if ($clubSlug) {
            $club = \App\Models\Club::where('slug', $clubSlug)->first();
            if ($club) {
                $user->clubs()->attach($club->id, [
                    'role' => 'member',
                    'member_number' => 'MEM-' . strtoupper(substr(uniqid(), -5)),
                    'status' => 'pending',
                ]);
            }
        }

        Auth::login($user);

        if (isset($club) && $club) {
            return redirect()->route('member.dashboard', $club->slug)
                ->with('success', 'Registration submitted! Your membership is pending admin approval.');
        }

        return redirect()->route('home')->with('success', 'Account created successfully! Welcome to Club Manager.');
    }
}
