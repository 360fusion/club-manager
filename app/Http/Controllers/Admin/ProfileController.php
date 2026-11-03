<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Support\EmailChange;
use App\Support\ImageDownscaler;
use App\Support\UploadRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/Profile/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url ? (str_starts_with($user->avatar_url, 'http') ? $user->avatar_url : asset('storage/'.$user->avatar_url)) : null,
                'pending_email' => $user->pending_email,
                'two_factor_enabled' => ! empty($user->two_factor_secret),
            ],
            'status' => session('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            // Changing the sign-in address hands over the account, so it needs the current password.
            'current_password' => [Rule::requiredIf(fn () => strcasecmp((string) $request->input('email'), (string) $user->email) !== 0), 'nullable', 'string', 'current_password'],
            'avatar_url' => ['nullable', 'string', 'max:1000', 'regex:#^(https?://|avatars/)#i'],
            'avatar' => UploadRules::image(4096),
        ]);

        $user->fill($request->only('name'));
        $emailHeld = strcasecmp((string) $request->input('email'), (string) $user->email) !== 0 && EmailChange::request($user, (string) $request->input('email'));

        if ($request->hasFile('avatar')) {
            ImageDownscaler::apply($request->file('avatar'), 800);
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_url = $path;
        } elseif ($request->filled('avatar_url')) {
            $user->avatar_url = $request->avatar_url;
        }

        $user->save();

        if ($emailHeld) {
            EmailChange::sendLink($user);
        }

        return redirect()->back()->with('success', $emailHeld ? "We sent a link to {$user->pending_email}. Your sign-in email stays {$user->email} until you open it." : 'Profile information updated successfully.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {

        $request->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->back()->with('success', 'Password updated successfully.');
    }
}
