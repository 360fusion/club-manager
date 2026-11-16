<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Launchpad\Http\Controllers\Auth\SsoController;
use Launchpad\Http\Controllers\Workspaces\SsoSettingsController;
use Launchpad\Workspaces\TeamAddresses;

// a team's own settings come first: on a team's subdomain /sso is this page, not the sign-in form below
Route::middleware(['auth', 'account.open', 'workspace'])->group(fn () => TeamAddresses::group(function () {
    Route::get('sso', [SsoSettingsController::class, 'show'])->name('workspace.sso');
    Route::put('sso', [SsoSettingsController::class, 'update'])->name('workspace.sso.update');
    Route::post('sso/verify', [SsoSettingsController::class, 'verify'])->middleware('throttle:10,1')->name('workspace.sso.verify');
    Route::delete('sso', [SsoSettingsController::class, 'destroy'])->middleware('password.confirm')->name('workspace.sso.destroy');
    Route::post('sso/scim', [SsoSettingsController::class, 'scimToken'])->middleware('password.confirm')->name('workspace.sso.scim');
    Route::delete('sso/scim', [SsoSettingsController::class, 'scimOff'])->name('workspace.sso.scim.off');
}));

// signing in with a company account (config launchpad.modules.sso)
Route::middleware('guest')->group(function () {
    Route::get('sso', [SsoController::class, 'create'])->name('sso.create');
    Route::post('sso', [SsoController::class, 'start'])->middleware('throttle:login')->name('sso.start');
});
// the provider sends the person back here; the state in the session is what proves it is the same sign-in
Route::get('sso/callback', [SsoController::class, 'callback'])->middleware('launchpad.public')->name('sso.callback');

// SAML: what the provider needs to know, and where it posts its answer from its own site (so no CSRF token: the signed answer, the request it
// answers and the one-time RelayState are checked instead)
Route::get('sso/saml/{connection}/metadata', [SsoController::class, 'samlMetadata'])->whereNumber('connection')->middleware('launchpad.public')->name('sso.saml.metadata');
Route::post('sso/saml/{connection}/acs', [SsoController::class, 'samlAcs'])->whereNumber('connection')->middleware(['launchpad.public', 'throttle:login'])
    ->withoutMiddleware(ValidateCsrfToken::class)->name('sso.saml.acs');
