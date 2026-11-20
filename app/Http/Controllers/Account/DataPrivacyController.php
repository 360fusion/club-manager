<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Support\AccountClosure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Closing your own account, from the profile page: a 30-day wait you can cancel. */
class DataPrivacyController extends Controller
{
    public function close(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        if ($blockers = AccountClosure::blockers($request->user())) {
            return back()->withErrors(['closure' => implode(' ', $blockers)]);
        }

        AccountClosure::request($request->user());

        return back()->with('success', 'Your account will be closed on '.AccountClosure::purgeDate($request->user()->fresh())->format('j F Y').'. Until then you can cancel here.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        AccountClosure::cancel($request->user());

        return back()->with('success', 'Closing your account has been cancelled.');
    }
}
