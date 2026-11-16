<?php

use Illuminate\Support\Facades\Route;
use Launchpad\Http\Controllers\ServiceTokenController;

Route::middleware(['auth', 'account.open', 'throttle:60,1'])->get('service-token', ServiceTokenController::class)->name('service-token');
