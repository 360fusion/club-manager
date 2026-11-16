<?php

use Illuminate\Support\Facades\Route;
use Launchpad\Accounts\LoginLinks;
use Launchpad\Accounts\Passkeys\Passkeys;
use Launchpad\Accounts\Waitlist;
use Launchpad\Http\Controllers\Account\ActivityController;
use Launchpad\Http\Controllers\Account\AnnouncementController;
use Launchpad\Http\Controllers\Account\AvatarController;
use Launchpad\Http\Controllers\Account\ConnectionsController;
use Launchpad\Http\Controllers\Account\InboxController;
use Launchpad\Http\Controllers\Account\NotificationsController;
use Launchpad\Http\Controllers\Account\PasskeyController;
use Launchpad\Http\Controllers\Account\PrivacyController;
use Launchpad\Http\Controllers\Account\ProfileController;
use Launchpad\Http\Controllers\Account\SecurityController;
use Launchpad\Http\Controllers\Account\SessionsController;
use Launchpad\Http\Controllers\Auth\AuthenticatedSessionController;
use Launchpad\Http\Controllers\Auth\ConfirmablePasswordController;
use Launchpad\Http\Controllers\Auth\EmailVerificationController;
use Launchpad\Http\Controllers\Auth\LoginLinkController;
use Launchpad\Http\Controllers\Auth\NewPasswordController;
use Launchpad\Http\Controllers\Auth\PasskeyLoginController;
use Launchpad\Http\Controllers\Auth\PasswordResetLinkController;
use Launchpad\Http\Controllers\Auth\RegisteredUserController;
use Launchpad\Http\Controllers\Auth\SocialLoginController;
use Launchpad\Http\Controllers\Auth\TwoFactorChallengeController;
use Launchpad\Http\Controllers\Auth\WaitlistController;

// the provider's callback serves both sign-in (a guest) and linking from the Security page (signed in)
Route::get('auth/{provider}/callback', [SocialLoginController::class, 'callback'])->whereIn('provider', ['google', 'facebook', 'azure'])->middleware('launchpad.public')->name('social.callback');

Route::middleware('guest')->group(function () {
    Route::get('auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])->whereIn('provider', ['google', 'facebook', 'azure'])->name('social.redirect');

    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:register');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:forgot-password')->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:reset-password')->name('password.store');

    if (Waitlist::enabled()) {
        Route::get('waitlist', [WaitlistController::class, 'create'])->name('waitlist');
        Route::post('waitlist', [WaitlistController::class, 'store'])->middleware('throttle:register');
    }

    if (LoginLinks::enabled()) {
        Route::get('login/link', [LoginLinkController::class, 'create'])->name('login.link');
        Route::post('login/link', [LoginLinkController::class, 'store'])->middleware('throttle:forgot-password');
        // the link opens a page with a button: a mail scanner that follows links does not use it up
        Route::get('login/link/{token}', [LoginLinkController::class, 'show'])->middleware('signed')->name('login.link.show');
        Route::post('login/link/{token}', [LoginLinkController::class, 'consume'])->middleware(['signed', 'throttle:10,1'])->name('login.link.consume');
    }

    if (Passkeys::enabled()) {
        Route::get('passkeys/options', [PasskeyLoginController::class, 'options'])->middleware('throttle:30,1')->name('passkey.options');
        Route::post('passkeys/login', [PasskeyLoginController::class, 'store'])->middleware('throttle:10,1')->name('passkey.login');
    }

    Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:5,1')->name('two-factor.verify');
});

// The link in the verification mail works from any browser, signed in or not: what it checks is its signature and the address it was made for
Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

Route::middleware(['auth', 'account.open'])->group(function () {
    Route::get('verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');
    Route::get('email/verification-status', [EmailVerificationController::class, 'status'])->middleware('throttle:30,1')->name('verification.status');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.confirm.store');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('account/security', [SecurityController::class, 'show'])->name('account.security');
    Route::put('password', [SecurityController::class, 'updatePassword'])->middleware('throttle:6,1')->name('password.update');
    Route::post('account/two-factor', [SecurityController::class, 'enableTwoFactor'])->name('two-factor.enable');
    Route::post('account/two-factor/confirm', [SecurityController::class, 'confirmTwoFactor'])->middleware('throttle:6,1')->name('two-factor.confirm');
    Route::post('account/two-factor/recovery-codes', [SecurityController::class, 'regenerateRecoveryCodes'])->middleware('password.confirm')->name('two-factor.recovery-codes');
    Route::get('account/profile', [ProfileController::class, 'show'])->name('account.profile');
    Route::put('account/profile', [ProfileController::class, 'update'])->middleware('throttle:6,1')->name('account.profile.update');
    Route::post('account/profile/avatar', [AvatarController::class, 'store'])->middleware('throttle:10,1')->name('account.avatar.store');
    Route::delete('account/profile/avatar', [AvatarController::class, 'destroy'])->name('account.avatar.destroy');
    Route::get('notifications', [InboxController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [InboxController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notifications/{id}/open', [InboxController::class, 'open'])->whereUuid('id')->name('notifications.open');
    Route::post('notifications/{id}/read', [InboxController::class, 'read'])->whereUuid('id')->name('notifications.read');
    Route::delete('notifications/{id}', [InboxController::class, 'destroy'])->whereUuid('id')->name('notifications.destroy');
    Route::get('account/notifications', [NotificationsController::class, 'show'])->name('account.notifications');
    Route::put('account/notifications', [NotificationsController::class, 'update'])->name('account.notifications.update');
    Route::get('account/activity', [ActivityController::class, 'show'])->name('account.activity');
    Route::post('announcements/{announcement}/dismiss', [AnnouncementController::class, 'dismiss'])->name('announcements.dismiss');
    Route::post('account/connections/{provider}', [ConnectionsController::class, 'connect'])->whereIn('provider', ['google', 'facebook', 'azure'])->name('account.connections.connect');
    Route::delete('account/connections/{provider}', [ConnectionsController::class, 'disconnect'])->whereIn('provider', ['google', 'facebook', 'azure'])->middleware('password.confirm')->name('account.connections.disconnect');
    Route::delete('account/sessions', [SessionsController::class, 'destroyOthers'])->middleware('password.confirm')->name('account.sessions.destroy');
    Route::get('account/privacy', [PrivacyController::class, 'show'])->name('account.privacy');
    Route::get('account/export', [PrivacyController::class, 'export'])->middleware('password.confirm')->name('account.export');
    Route::post('account/close', [PrivacyController::class, 'close'])->middleware('password.confirm')->name('account.close');
    Route::delete('account/two-factor', [SecurityController::class, 'disableTwoFactor'])->middleware('password.confirm')->name('two-factor.disable');

    if (Passkeys::enabled()) {
        Route::post('account/passkeys/options', [PasskeyController::class, 'options'])->middleware('throttle:20,1')->name('account.passkeys.options');
        Route::post('account/passkeys', [PasskeyController::class, 'store'])->middleware('throttle:10,1')->name('account.passkeys.store');
        Route::delete('account/passkeys/{passkey}', [PasskeyController::class, 'destroy'])->whereNumber('passkey')->middleware('password.confirm')->name('account.passkeys.destroy');
    }
});
