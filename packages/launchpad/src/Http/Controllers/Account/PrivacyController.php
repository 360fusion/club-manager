<?php

namespace Launchpad\Http\Controllers\Account;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Launchpad\Accounts\AccountClosure;
use Launchpad\Http\Ui;

/** Data and privacy: a copy of what the account holds, and closing the account */
class PrivacyController extends Controller
{
    public function show(Request $request, AccountClosure $closure): View|Responsable
    {
        return Ui::render('account.privacy', ['retentionDays' => $closure->retentionDays()]);
    }

    public function export(Request $request)
    {
        $user = $request->user();

        $data = [
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'terms_version' => $user->terms_version,
                'terms_accepted_at' => $user->terms_accepted_at?->toIso8601String(),
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
            ],
            'sign_in_with' => Schema::hasTable('social_accounts') ? $user->socialAccounts()->pluck('provider')->all() : [],
            'workspaces' => (Schema::hasTable('memberships') ? $user->memberships()->with('workspace')->get() : collect())->map(fn ($m) => [
                'name' => $m->workspace->name,
                'role' => $m->role->value,
                'joined_at' => $m->created_at?->toIso8601String(),
            ])->all(),
        ];

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="my-data-'.now()->format('Y-m-d').'.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function close(Request $request, AccountClosure $closure): RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'in:CLOSE']], ['confirm.in' => __('Type CLOSE to confirm.')]);

        $closure->close($request->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(config('launchpad.routes.home'))->with('status', __('Your account has been closed.'));
    }
}
