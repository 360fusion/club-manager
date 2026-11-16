<?php

use Illuminate\Support\Facades\Route;
use Launchpad\Http\Controllers\Site\ContactController;
use Launchpad\Http\Controllers\Site\HelpController;

Route::middleware('launchpad.public')->group(function () {
    if (config('launchpad.modules.help')) {
        Route::get('help', [HelpController::class, 'hub'])->name('help');
        Route::get('help/search', [HelpController::class, 'search'])->name('help.search');
        Route::get('help/topic/{category}', [HelpController::class, 'category'])->where('category', '[a-z0-9-]+')->name('help.category');
        Route::get('help/{slug}', [HelpController::class, 'article'])->where('slug', '[a-z0-9-]+')->name('help.article');
        Route::post('help/{slug}/feedback', [HelpController::class, 'feedback'])->where('slug', '[a-z0-9-]+')->middleware('throttle:help-feedback')->name('help.feedback');
        Route::get('faq', [HelpController::class, 'faqs'])->name('faq');
        Route::get('faq/{id}', [HelpController::class, 'faq'])->where('id', '[a-z0-9-]+')->name('faq.show');
    }

    if (config('launchpad.modules.contact')) {
        Route::get('contact', [ContactController::class, 'show'])->name('contact');
        Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.send');
    }
});
