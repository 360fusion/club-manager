<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailChangeController extends Controller
{
    /** The link in the mail to the new address (signed, so it cannot be made up; it works whether or not the person is signed in here). */
    public function confirm(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        abort_unless(filled($user->pending_email) && hash_equals(sha1($user->pending_email), $hash), 403, 'This link is no longer valid.');

        $destination = $request->user() ? route('profile.edit') : route('login');

        if (User::where('email', $user->pending_email)->whereKeyNot($user->id)->exists()) {
            $user->forceFill(['pending_email' => null])->save();

            return redirect($destination)->with('error', 'That email address is now used by another account, so it was not changed.');
        }

        $user->forceFill(['email' => $user->pending_email, 'pending_email' => null, 'email_verified_at' => now()])->save();

        return redirect($destination)->with('success', 'Your sign-in email is now '.$user->email.'.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['pending_email' => null])->save();

        return back()->with('success', 'The email change was cancelled.');
    }
}
