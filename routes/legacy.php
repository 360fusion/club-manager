<?php

use App\Http\Controllers\ClubShortLinkController;
use App\Http\Controllers\MemberCalendarController;
use App\Http\Controllers\SuperAdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Old addresses that redirect, and the single-segment /{slug} route, which has to stay last.
// Dev Utility Helper Route
Route::post('/auth/make-me-superadmin', [SuperAdminController::class, 'makeMeSuperAdmin'])->middleware('auth')->name('auth.make_me_superadmin');

// Old member addresses, from before everything moved under /members.
Route::redirect('/members', '/members/dashboard', 301);
Route::redirect('/portal/subscriptions', '/members/subscriptions', 301);
Route::redirect('/admin/profile', '/members/profile', 301);
Route::redirect('/admin/profile/two-factor', '/members/security', 301);
Route::get('/directory', fn (Request $request) => redirect('/members/directory'.($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301));
Route::redirect('/{slug}/events', '/members/{slug}/events', 301);
Route::redirect('/{slug}/dues', '/members/{slug}/dues', 301);
Route::redirect('/{slug}/profile', '/members/{slug}/profile', 301);
Route::redirect('/{slug}/clubs', '/members/dashboard', 301);
Route::redirect('/{slug}/news/{id}', '/members/{slug}/news/{id}', 301);
Route::redirect('/{slug}/meetings/{id}/summons', '/members/{slug}/meetings/{id}/summons', 301);
Route::redirect('/{slug}/meetings/{id}/pdf', '/members/{slug}/meetings/{id}/pdf', 301);

// The personal calendar feed is authenticated by the secret token in its URL.
Route::get('/calendar/{token}.ics', [MemberCalendarController::class, 'feed'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('members.calendar.feed');

// A club's short link: members go to their member area, everyone else to the public site.
// This is a single-segment route, so it has to stay last; the {slug} constraint keeps
// reserved words (login, members, site...) out of it.
Route::get('/{slug}', ClubShortLinkController::class)->name('clubs.short');
