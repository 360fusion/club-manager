<?php

use Illuminate\Support\Facades\Route;
use Launchpad\Http\Controllers\Billing\BillingController;
use Launchpad\Http\Controllers\Billing\CheckoutController;
use Launchpad\Http\Controllers\Billing\PlanSignupController;
use Launchpad\Http\Controllers\Billing\PricingController;
use Launchpad\Workspaces\TeamAddresses;

Route::get('pricing', [PricingController::class, 'index'])->middleware('launchpad.public')->name('pricing');

// buy a plan without an account: made on the way
Route::middleware('launchpad.public')->group(function () {
    Route::get('checkout/{plan}/{frequency}/account', [PlanSignupController::class, 'show'])->name('checkout.account');
    Route::post('checkout/{plan}/{frequency}/account', [PlanSignupController::class, 'storeAccount'])->middleware(['guest', 'throttle:register'])->name('checkout.account.store');
});

Route::middleware(['auth', 'account.open'])->group(function () {
    Route::get('checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('checkout/cancelled', [CheckoutController::class, 'cancelled'])->name('checkout.cancelled');
    Route::get('checkout/{plan}/{frequency}', [CheckoutController::class, 'start'])->middleware('verified.policy:action')->name('checkout.start');

    TeamAddresses::group(fn () => Route::prefix('billing')->middleware('workspace:manage-billing')->group(function () {
        Route::get('/', [BillingController::class, 'show'])->name('workspace.billing');
        Route::post('portal', [BillingController::class, 'portal'])->name('workspace.billing.portal');
        Route::get('change/{plan}/{frequency}', [BillingController::class, 'review'])->name('workspace.billing.change');
        Route::post('change/{plan}/{frequency}', [BillingController::class, 'change'])->middleware('verified.policy:action')->name('workspace.billing.change.start');
        Route::post('cancel', [BillingController::class, 'cancel'])->name('workspace.billing.cancel');
    }));
});
