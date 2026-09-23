<?php

namespace App\Http\Controllers\Auth;

use App\Domains\ClubAccounting\Services\MemberInvitationService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * Confirm an email address from the signed link sent at sign-up.
     */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        // Now the address is proven, tie the person to the roster record it matches at any club that has approved them.
        $invitations = app(MemberInvitationService::class);
        $user->clubs()->wherePivot('status', 'active')->get()->each(fn ($club) => $invitations->linkMatchingMember($club, $user));

        return redirect()->route('login')->with('success', 'Email confirmed. You can now sign in.');
    }
}
