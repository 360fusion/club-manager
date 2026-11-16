<?php

use Illuminate\Support\Facades\Route;
use Launchpad\Http\Controllers\Scim\ScimController;
use Launchpad\Http\Middleware\AuthenticateScim;

// a team's directory adding and removing its people (SCIM 2.0); the token comes from the team's single sign-on page
Route::prefix('scim/v2')->middleware([AuthenticateScim::class, 'throttle:300,1'])->group(function () {
    Route::get('ServiceProviderConfig', [ScimController::class, 'serviceProviderConfig']);
    Route::get('ResourceTypes', [ScimController::class, 'resourceTypes']);
    Route::get('Schemas', [ScimController::class, 'schemas']);
    Route::get('Users', [ScimController::class, 'index'])->name('scim.users');
    Route::post('Users', [ScimController::class, 'store']);
    Route::get('Users/{id}', [ScimController::class, 'show'])->whereNumber('id')->name('scim.users.show');
    Route::put('Users/{id}', [ScimController::class, 'replace'])->whereNumber('id');
    Route::patch('Users/{id}', [ScimController::class, 'patch'])->whereNumber('id');
    Route::delete('Users/{id}', [ScimController::class, 'destroy'])->whereNumber('id');
});
