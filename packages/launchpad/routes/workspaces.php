<?php

use Illuminate\Support\Facades\Route;
use Launchpad\Http\Controllers\Workspaces\ActivityController;
use Launchpad\Http\Controllers\Workspaces\ApiTokenController;
use Launchpad\Http\Controllers\Workspaces\ApiTokenUsageController;
use Launchpad\Http\Controllers\Workspaces\FileController;
use Launchpad\Http\Controllers\Workspaces\InvitationController;
use Launchpad\Http\Controllers\Workspaces\MediaController;
use Launchpad\Http\Controllers\Workspaces\MediaFolderController;
use Launchpad\Http\Controllers\Workspaces\MemberController;
use Launchpad\Http\Controllers\Workspaces\OnboardingController;
use Launchpad\Http\Controllers\Workspaces\WebhookController;
use Launchpad\Http\Controllers\Workspaces\WorkspaceController;
use Launchpad\Workspaces\TeamAddresses;

if (config('launchpad.modules.files')) {
    // a link made by WorkspaceFile::signedUrl(): the signature is the permission
    Route::get('files/{file}/signed', [FileController::class, 'signed'])->middleware(['launchpad.public', 'signed'])->name('files.signed');
}

// the rest is pages: an app with pages of its own leaves them out and keeps the back end (launchpad.workspaces.pages)
if (! config('launchpad.workspaces.pages', true)) {
    return;
}

// with team subdomains on, old /w/{slug}/... links (bookmarks, mails already sent) go to the team's subdomain
if (TeamAddresses::enabled()) {
    Route::get('w/{slug}/{rest?}', fn (string $slug, ?string $rest = null) => redirect()->away(TeamAddresses::url($slug, (string) $rest).(request()->getQueryString() ? '?'.request()->getQueryString() : ''), 301))
        ->where('rest', '.*')->middleware('launchpad.public');
}

// the link in an invitation mail: open to anyone, because the person may not have an account yet
Route::get('invitations/{token}', [InvitationController::class, 'show'])->middleware('launchpad.public')->name('invitations.show');

Route::middleware(['auth', 'account.open'])->group(function () {
    Route::post('invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');

    Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('workspaces', [WorkspaceController::class, 'store'])->middleware('verified.policy:action')->name('workspaces.store');
    Route::post('workspaces/{slug}/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');

    TeamAddresses::group(function () {
        Route::middleware('workspace')->group(function () {
            // a team's own address opens its people page
            if (TeamAddresses::enabled()) {
                Route::get('/', fn (string $slug) => redirect()->route('workspace.members', $slug))->name('workspace.home');
            }
            Route::get('members', [MemberController::class, 'index'])->name('workspace.members');
            Route::post('leave', [WorkspaceController::class, 'leave'])->name('workspace.leave');
            Route::post('onboarding/dismiss', [OnboardingController::class, 'dismiss'])->name('workspace.onboarding.dismiss');

            if (config('launchpad.modules.files')) {
                Route::get('files', [FileController::class, 'index'])->name('workspace.files');
                Route::post('files', [FileController::class, 'store'])->name('workspace.files.store');
                Route::get('files/{file}', [FileController::class, 'download'])->name('workspace.files.download');
                Route::delete('files/{file}', [FileController::class, 'destroy'])->name('workspace.files.destroy');
            }

            if (config('launchpad.modules.media')) {
                Route::get('media', [MediaController::class, 'index'])->name('workspace.media');
                Route::get('media/browse', [MediaController::class, 'browseJson'])->name('workspace.media.browse');
                Route::post('media', [MediaController::class, 'store'])->name('workspace.media.store');
                Route::post('media/check', [MediaController::class, 'check'])->name('workspace.media.check');
                Route::post('media/folders', [MediaFolderController::class, 'store'])->name('workspace.media.folders.store');
                Route::patch('media/folders/{folder}', [MediaFolderController::class, 'update'])->name('workspace.media.folders.update');
                Route::delete('media/folders/{folder}', [MediaFolderController::class, 'trash'])->name('workspace.media.folders.trash');
                Route::post('media/folders/{folder}/restore', [MediaFolderController::class, 'restore'])->name('workspace.media.folders.restore');
                Route::delete('media/folders/{folder}/forever', [MediaFolderController::class, 'destroy'])->name('workspace.media.folders.destroy');
                Route::get('media/{file}', [MediaController::class, 'show'])->name('workspace.media.show');
                Route::patch('media/{file}', [MediaController::class, 'update'])->name('workspace.media.update');
                Route::delete('media/{file}', [MediaController::class, 'trash'])->name('workspace.media.trash');
                Route::post('media/{file}/restore', [MediaController::class, 'restore'])->name('workspace.media.restore');
                Route::delete('media/{file}/forever', [MediaController::class, 'destroy'])->name('workspace.media.destroy');
            }
        });

        Route::middleware('workspace:manage-workspace')->group(function () {
            Route::get('settings', [WorkspaceController::class, 'settings'])->name('workspace.settings');
            Route::put('settings', [WorkspaceController::class, 'update'])->name('workspace.settings.update');
            Route::get('activity', [ActivityController::class, 'index'])->name('workspace.activity');
            Route::get('activity/download', [ActivityController::class, 'download'])->middleware('throttle:10,1')->name('workspace.activity.download');
            Route::put('settings/roles', [WorkspaceController::class, 'updateRoles'])->name('workspace.settings.roles');
            Route::delete('settings/roles', [WorkspaceController::class, 'resetRoles'])->name('workspace.settings.roles.reset');
            Route::post('settings/custom-roles', [WorkspaceController::class, 'storeCustomRole'])->name('workspace.custom-roles.store');
            Route::put('settings/custom-roles/{customRole}', [WorkspaceController::class, 'updateCustomRole'])->name('workspace.custom-roles.update');
            Route::delete('settings/custom-roles/{customRole}', [WorkspaceController::class, 'destroyCustomRole'])->name('workspace.custom-roles.destroy');

            if (config('launchpad.modules.api')) {
                Route::get('api', [ApiTokenController::class, 'index'])->name('workspace.api');
                Route::post('api', [ApiTokenController::class, 'store'])->name('workspace.api.store');
                Route::get('api/{token}', [ApiTokenUsageController::class, 'show'])->name('workspace.api.show');
                Route::post('api/{token}/rotate', [ApiTokenController::class, 'rotate'])->name('workspace.api.rotate');
                Route::delete('api/{token}', [ApiTokenController::class, 'destroy'])->name('workspace.api.destroy');
            }

            if (config('launchpad.modules.webhooks')) {
                Route::get('webhooks', [WebhookController::class, 'index'])->name('workspace.webhooks');
                Route::post('webhooks', [WebhookController::class, 'store'])->name('workspace.webhooks.store');
                Route::put('webhooks/{endpoint}', [WebhookController::class, 'update'])->name('workspace.webhooks.update');
                Route::delete('webhooks/{endpoint}', [WebhookController::class, 'destroy'])->name('workspace.webhooks.destroy');
                Route::post('webhooks/{endpoint}/test', [WebhookController::class, 'test'])->name('workspace.webhooks.test');
            }
        });

        Route::middleware(['workspace:manage-members', 'verified.policy:strict'])->group(function () {
            Route::post('invitations', [InvitationController::class, 'store'])->name('workspace.invitations.store');
            Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('workspace.invitations.destroy');
            Route::put('members/{membership}/permissions', [MemberController::class, 'permissions'])->name('members.permissions');
            Route::put('members/{membership}', [MemberController::class, 'update'])->name('members.update');
            Route::delete('members/{membership}', [MemberController::class, 'destroy'])->name('members.destroy');
        });

        Route::middleware(['workspace:transfer', 'password.confirm'])->post('transfer', [WorkspaceController::class, 'transfer'])->name('workspace.transfer');
        Route::middleware(['workspace:delete', 'password.confirm'])->delete('/', [WorkspaceController::class, 'destroy'])->name('workspace.destroy');
    });
});
