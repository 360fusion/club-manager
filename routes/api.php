<?php

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Served under /api by the stateless `api` middleware group: no session and no CSRF token, which
// app clients that sign in with an email and password for a token cannot send.
Route::prefix('v1')->group(function () {
    Route::post('/tokens/create', [ApiController::class, 'issueToken'])->name('api.tokens.create')->middleware('throttle:api-token');
    Route::get('/clubs/{slug}/info', [ApiController::class, 'getClubInfo'])->middleware('throttle:60,1')->name('api.clubs.info');

    Route::middleware('auth:sanctum')->get('/user', fn (Request $request) => $request->user());
});
