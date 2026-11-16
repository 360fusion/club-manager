<?php

use Illuminate\Support\Facades\Route;
use Launchpad\Http\Controllers\Site\BlogController;

Route::middleware('launchpad.public')->group(function () {
    Route::get('blog', [BlogController::class, 'index'])->name('blog.index');
    Route::get('blog/feed.xml', [BlogController::class, 'feed'])->name('blog.feed');
    Route::get('blog/{slug}', [BlogController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('blog.show');
});
