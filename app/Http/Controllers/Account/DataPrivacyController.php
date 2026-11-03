<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Support\AccountClosure;
use App\Support\PersonalDataExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A person's own data: download it, or close the account (a 30-day wait they can cancel) */
class DataPrivacyController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Account/Privacy', [
            'closureRequestedAt' => $user->closure_requested_at?->toIso8601String(),
            'purgeDate' => AccountClosure::purgeDate($user)?->format('j F Y'),
            'waitDays' => AccountClosure::WAIT_DAYS,
            'blockers' => AccountClosure::blockers($user),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        // Its own error bag: this form is a plain post, and its error must not show under the close form's password box.
        $request->validateWithBag('export', ['password' => ['required', 'current_password']]);

        $data = PersonalDataExport::for($request->user());

        return response()->streamDownload(
            fn () => print json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'my-data-'.now()->format('Y-m-d').'.json',
            ['Content-Type' => 'application/json'],
        );
    }

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
