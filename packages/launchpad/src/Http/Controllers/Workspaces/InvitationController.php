<?php

namespace Launchpad\Http\Controllers\Workspaces;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Launchpad\Http\Ui;
use Launchpad\Models\Invitation;
use Launchpad\Workspaces\CurrentWorkspace;
use Launchpad\Workspaces\Invitations;
use Launchpad\Workspaces\Workspaces;

class InvitationController extends Controller
{
    public function store(Request $request, CurrentWorkspace $current, Invitations $invitations): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'role' => ['required', 'string']]);
        [$role, $custom] = app(Workspaces::class)->parseRole($current->workspace, $data['role']);

        $ticks = [];
        foreach (array_keys((array) config('launchpad.workspaces.permissions')) as $key) {
            $ticks[$key] = $request->boolean("permissions.{$key}");
        }

        $invitations->invite($current->workspace, $data['email'], $role, $request->user(), $ticks, $custom);

        return back()->with('status', 'Invitation sent.');
    }

    public function destroy(Request $request, string $slug, Invitation $invitation, CurrentWorkspace $current, Invitations $invitations): RedirectResponse
    {
        $invitations->revoke($current->workspace, $invitation, $request->user());

        return back()->with('status', 'Invitation withdrawn.');
    }

    /** The page the link in the mail opens: public, because the person may not have an account yet */
    public function show(Request $request, string $token, Invitations $invitations): View|Responsable
    {
        $invitation = $invitations->find($token);
        $user = $request->user();

        if ($invitation && ! $user) {
            // after signing in or up, the person comes back to this page
            $request->session()->put('url.intended', url()->current());
        }

        $rightAccount = $invitation && $user && strtolower($user->email) === $invitation->email;

        return Ui::render('workspaces.invitation', ['invitation' => $invitation, 'token' => $token, 'user' => $user, 'rightAccount' => $rightAccount], fn () => [
            'invitation' => $invitation ? ['email' => $invitation->email, 'roleLabel' => $invitation->roleLabel(), 'workspace' => $invitation->workspace->name, 'inviter' => $invitation->inviter?->name] : null,
            'token' => $token,
            'userEmail' => $user?->email,
            'rightAccount' => (bool) $rightAccount,
        ]);
    }

    public function accept(Request $request, string $token, Invitations $invitations): RedirectResponse
    {
        $invitation = $invitations->find($token);
        abort_if($invitation === null, 404);

        $membership = $invitations->accept($invitation, $request->user());

        if ($membership === null) {
            return back()->withErrors(['invitation' => 'This invitation is for a different, or unverified, email address.']);
        }

        return redirect()->route('workspace.members', $invitation->workspace->slug)->with('status', 'You have joined '.$invitation->workspace->name.'.');
    }
}
