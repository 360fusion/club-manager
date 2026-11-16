<?php

use Illuminate\Support\Facades\Route;
use Launchpad\Http\Controllers\Admin\ImpersonationController;

// staff, signed in to the admin, open this from a user's page; the permission is checked again in the controller
Route::get('impersonate/{user}', [ImpersonationController::class, 'start'])->middleware(['signed', 'auth:admin'])->name('impersonate.start');
Route::post('impersonate/stop', [ImpersonationController::class, 'stop'])->middleware('auth')->name('impersonate.stop');
