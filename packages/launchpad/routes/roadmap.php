<?php

use Illuminate\Support\Facades\Route;
use Launchpad\Http\Controllers\Site\RoadmapController;

Route::get('roadmap', [RoadmapController::class, 'index'])->middleware('launchpad.public')->name('roadmap');

Route::middleware(['auth', 'account.open'])->group(function () {
    Route::post('roadmap', [RoadmapController::class, 'store'])->middleware(['verified.policy:action', 'throttle:5,1'])->name('roadmap.store');
    Route::post('roadmap/{item}/vote', [RoadmapController::class, 'toggle'])->whereNumber('item')->middleware('throttle:30,1')->name('roadmap.vote');
});
