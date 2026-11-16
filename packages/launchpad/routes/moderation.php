<?php

use Illuminate\Support\Facades\Route;
use Launchpad\Http\Controllers\ReportController;

Route::middleware(['auth', 'account.open', 'throttle:10,1'])->post('reports', [ReportController::class, 'store'])->name('reports.store');
