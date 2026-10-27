<?php

use App\Http\Controllers\PlatformPaymentsAdminController;
use App\Http\Controllers\SuperAdminClubTypeController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SuperAdminGrandLodgeController;
use App\Http\Controllers\SuperAdminHallController;
use App\Http\Controllers\SuperAdminLodgeClaimController;
use App\Http\Controllers\SuperAdminLodgeController;
use App\Http\Controllers\SuperAdminLodgeCorrectionController;
use App\Http\Controllers\SuperAdminProvinceController;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use Illuminate\Support\Facades\Route;

// Platform super admin console.
// Superadmin Management Console Routes
Route::middleware(['auth', EnsureUserIsSuperAdmin::class])->group(function () {
    Route::get('/superadmin', [SuperAdminController::class, 'dashboard'])->name('superadmin.dashboard');
    Route::get('/superadmin/club-types', [SuperAdminClubTypeController::class, 'clubTypesIndex'])->name('superadmin.club_types.index');
    Route::get('/superadmin/club-types/{id}', [SuperAdminClubTypeController::class, 'showClubType'])->name('superadmin.club_types.show');
    Route::post('/superadmin/club-types', [SuperAdminClubTypeController::class, 'storeClubType'])->name('superadmin.club_types.store');
    Route::put('/superadmin/club-types/{id}', [SuperAdminClubTypeController::class, 'updateClubType'])->name('superadmin.club_types.update');
    Route::get('/superadmin/email-templates', [SuperAdminClubTypeController::class, 'emailTemplatesIndex'])->name('superadmin.email_templates.index');
    Route::put('/superadmin/email-templates/{id}', [SuperAdminClubTypeController::class, 'updateEmailTemplate'])->name('superadmin.email_templates.update');
    Route::post('/superadmin/email-templates/{id}/test', [SuperAdminClubTypeController::class, 'sendTestEmailTemplate'])->middleware('throttle:10,1')->name('superadmin.email_templates.test');
    Route::get('/superadmin/platform-payments', [PlatformPaymentsAdminController::class, 'index'])->name('superadmin.platform_payments.index');
    Route::get('/superadmin/platform-payments/export', [PlatformPaymentsAdminController::class, 'export'])->name('superadmin.platform_payments.export');
    Route::put('/superadmin/platform-payments/{id}', [PlatformPaymentsAdminController::class, 'update'])->name('superadmin.platform_payments.update');
    Route::get('/superadmin/grand-lodges', [SuperAdminGrandLodgeController::class, 'grandLodgesIndex'])->name('superadmin.grand_lodges.index');
    Route::post('/superadmin/grand-lodges', [SuperAdminGrandLodgeController::class, 'storeGrandLodge'])->name('superadmin.grand_lodges.store');
    Route::put('/superadmin/grand-lodges/{id}', [SuperAdminGrandLodgeController::class, 'updateGrandLodge'])->name('superadmin.grand_lodges.update');
    Route::get('/superadmin/grand-lodges/{id}/ranks', [SuperAdminGrandLodgeController::class, 'grandLodgeRanks'])->name('superadmin.grand_lodges.ranks');
    Route::put('/superadmin/grand-lodges/{id}/ranks', [SuperAdminGrandLodgeController::class, 'updateGrandLodgeRanks'])->name('superadmin.grand_lodges.ranks.update');
    Route::delete('/superadmin/grand-lodges/{id}', [SuperAdminGrandLodgeController::class, 'destroyGrandLodge'])->name('superadmin.grand_lodges.destroy');
    Route::get('/superadmin/provinces', [SuperAdminProvinceController::class, 'provincesIndex'])->name('superadmin.provinces.index');
    Route::get('/superadmin/provinces/{id}', [SuperAdminProvinceController::class, 'showProvince'])->name('superadmin.provinces.show');
    Route::get('/superadmin/provinces/{id}/edit', [SuperAdminProvinceController::class, 'editProvince'])->name('superadmin.provinces.edit');
    Route::post('/superadmin/provinces', [SuperAdminProvinceController::class, 'storeProvince'])->name('superadmin.provinces.store');
    Route::put('/superadmin/provinces/{id}', [SuperAdminProvinceController::class, 'updateProvince'])->name('superadmin.provinces.update');
    Route::delete('/superadmin/provinces/{id}', [SuperAdminProvinceController::class, 'destroyProvince'])->name('superadmin.provinces.destroy');
    Route::post('/superadmin/provinces/{id}/admins', [SuperAdminProvinceController::class, 'addAdmin'])->name('superadmin.provinces.admins.store');
    Route::delete('/superadmin/provinces/{id}/admins/{userId}', [SuperAdminProvinceController::class, 'removeAdmin'])->name('superadmin.provinces.admins.destroy');

    // Districts and Groups
    Route::get('/superadmin/districts', [SuperAdminProvinceController::class, 'districtsIndex'])->name('superadmin.districts.index');
    Route::get('/superadmin/districts/{id}', [SuperAdminProvinceController::class, 'showDistrict'])->name('superadmin.districts.show');
    Route::get('/superadmin/districts/{id}/edit', [SuperAdminProvinceController::class, 'editDistrict'])->name('superadmin.districts.edit');
    Route::post('/superadmin/districts', [SuperAdminProvinceController::class, 'storeDistrict'])->name('superadmin.districts.store');
    Route::put('/superadmin/districts/{id}', [SuperAdminProvinceController::class, 'updateDistrict'])->name('superadmin.districts.update');
    Route::delete('/superadmin/districts/{id}', [SuperAdminProvinceController::class, 'destroyDistrict'])->name('superadmin.districts.destroy');

    // Lodge directory
    Route::get('/superadmin/lodges', [SuperAdminLodgeController::class, 'index'])->name('superadmin.lodges.index');
    Route::post('/superadmin/lodges', [SuperAdminLodgeController::class, 'store'])->name('superadmin.lodges.store');
    Route::put('/superadmin/lodges/{id}', [SuperAdminLodgeController::class, 'update'])->name('superadmin.lodges.update');
    Route::post('/superadmin/lodges/{id}/link-club', [SuperAdminLodgeController::class, 'linkClub'])->name('superadmin.lodges.link_club');
    Route::delete('/superadmin/lodges/{id}/link-club', [SuperAdminLodgeController::class, 'unlinkClub'])->name('superadmin.lodges.unlink_club');
    Route::get('/superadmin/lodge-claims', [SuperAdminLodgeClaimController::class, 'index'])->name('superadmin.lodge_claims.index');
    Route::post('/superadmin/lodge-claims/{id}/approve', [SuperAdminLodgeClaimController::class, 'approve'])->name('superadmin.lodge_claims.approve');
    Route::post('/superadmin/lodge-claims/{id}/reject', [SuperAdminLodgeClaimController::class, 'reject'])->name('superadmin.lodge_claims.reject');
    Route::post('/superadmin/lodge-claims/{id}/request-info', [SuperAdminLodgeClaimController::class, 'requestInfo'])->name('superadmin.lodge_claims.request_info');
    Route::get('/superadmin/lodge-corrections', [SuperAdminLodgeCorrectionController::class, 'index'])->name('superadmin.lodge_corrections.index');
    Route::post('/superadmin/lodge-corrections/{id}/apply', [SuperAdminLodgeCorrectionController::class, 'apply'])->name('superadmin.lodge_corrections.apply');
    Route::post('/superadmin/lodge-corrections/{id}/reject', [SuperAdminLodgeCorrectionController::class, 'reject'])->name('superadmin.lodge_corrections.reject');
    Route::delete('/superadmin/lodges/{id}', [SuperAdminLodgeController::class, 'destroy'])->name('superadmin.lodges.destroy');

    // Masonic halls
    Route::get('/superadmin/masonic-halls', [SuperAdminHallController::class, 'masonicHallsIndex'])->name('superadmin.masonic_halls.index');
    Route::post('/superadmin/masonic-halls', [SuperAdminHallController::class, 'storeMasonicHall'])->name('superadmin.masonic_halls.store');
    Route::put('/superadmin/masonic-halls/{id}', [SuperAdminHallController::class, 'updateMasonicHall'])->name('superadmin.masonic_halls.update');
    Route::delete('/superadmin/masonic-halls/{id}', [SuperAdminHallController::class, 'destroyMasonicHall'])->name('superadmin.masonic_halls.destroy');

    Route::get('/superadmin/australian-grand-lodges', [SuperAdminGrandLodgeController::class, 'australianGrandLodges'])->name('superadmin.australian_grand_lodges');
    Route::get('/superadmin/us-grand-lodges', [SuperAdminGrandLodgeController::class, 'usGrandLodges'])->name('superadmin.us_grand_lodges');
});
