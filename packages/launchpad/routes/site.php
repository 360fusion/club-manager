<?php

use Illuminate\Support\Facades\Route;
use Launchpad\Http\Controllers\Site\ConsentController;
use Launchpad\Http\Controllers\Site\PageController;
use Launchpad\Http\Controllers\Site\SeoController;

Route::middleware('launchpad.public')->group(function () {
    if (config('launchpad.site.home')) {
        Route::get('/', [PageController::class, 'home'])->name('home');
    }

    // one route for each default page that is switched on; the page is read from its file when asked for
    foreach (array_keys(array_filter((array) config('launchpad.site.pages'))) as $slug) {
        Route::get("/{$slug}", [PageController::class, 'show'])->defaults('slug', $slug)->name("page.{$slug}");
    }

    Route::get('cookie-preferences', [ConsentController::class, 'show'])->name('cookie-preferences');
    Route::post('cookie-consent', [ConsentController::class, 'store'])->name('cookie-consent');

    Route::get('robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
    Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
    Route::get('llms.txt', [SeoController::class, 'llms'])->name('seo.llms');
    Route::get('.well-known/security.txt', [SeoController::class, 'securityTxt'])->name('seo.security');
});
