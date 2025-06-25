<?php

use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public webhook routes (no auth required)
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('/{gateway}', [PaymentController::class, 'handleWebhookWithQueue'])
        ->name('payment')
        ->where('gateway', 'paddle|payproglobal|fastspring');
});

// Public success/cancel handlers
Route::middleware('web')->group(function () {
    Route::match(['get', 'post'], '/payments/success', [PaymentController::class, 'handleSuccess'])
        ->name('payments.success');
    Route::get('/payments/cancel', [PaymentController::class, 'handleCancel'])
        ->name('payments.cancel');
});

// Authenticated API routes
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    // User info
    Route::get('/user', function (Request $request) {
        return $request->user()->load(['package', 'paymentGateway']);
    });

    // Upgrade routes
    Route::prefix('subscription')->name('subscription.')->group(function () {
        // Check upgrade eligibility
        Route::get('/upgrade/eligibility', [PaymentController::class, 'getUpgradeEligibility'])
            ->name('upgrade.eligibility');
        
        // Process upgrade
        Route::post('/upgrade/{package}', [PaymentController::class, 'upgradeSubscription'])
            ->name('upgrade.process')
            ->middleware('throttle:5,1'); // Limit upgrade attempts
    });

    // Payment routes
    Route::prefix('payments')->name('payments.')->group(function () {
        // Checkout endpoints
        Route::post('/paddle/checkout/{package}', [PaymentController::class, 'paddleCheckout'])
            ->name('paddle.checkout')
            ->middleware('throttle:10,1');

        Route::post('/fastspring/checkout/{package}', [PaymentController::class, 'fastspringCheckout'])
            ->name('fastspring.checkout')
            ->middleware('throttle:10,1');

        Route::post('/payproglobal/checkout/{package}', [PaymentController::class, 'payProGlobalCheckout'])
            ->name('payproglobal.checkout')
            ->middleware('throttle:10,1');

        // Orders management
        Route::get('/orders', [PaymentController::class, 'getOrdersList'])
            ->name('orders.index');
    });
});
