<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public webhook routes
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('/paddle', [PaymentController::class, 'handlePaddleWebhook'])->name('paddle');
    Route::post('/fastspring', [PaymentController::class, 'handleFastSpringWebhook'])->name('fastspring');
    Route::post('/payproglobal', [PaymentController::class, 'handlePayProGlobalWebhook'])->name('payproglobal');
});

// Authenticated API routes
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user()->load(['package', 'paymentGateway']);
    })->name('user.info');

    // Payment routes
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::post('/paddle/checkout/{package}', [PaymentController::class, 'paddleCheckout'])
            ->name('paddle.checkout')
            ->middleware('throttle:10,1');

        Route::post('/fastspring/checkout/{package}', [PaymentController::class, 'fastspringCheckout'])
            ->name('fastspring.checkout')
            ->middleware('throttle:10,1');

        Route::post('/payproglobal/checkout/{package}', [PaymentController::class, 'payProGlobalCheckout'])
            ->name('payproglobal.checkout')
            ->middleware('throttle:10,1');

        Route::get('/verify-payproglobal/{paymentReference}', [PaymentController::class, 'verifyPayProGlobalPaymentStatus'])
            ->name('verify-payproglobal');

        Route::post('/upgrade', [PaymentController::class, 'upgradeSubscription'])
            ->name('upgrade')
            ->middleware('throttle:10,1');

        Route::post('/cancel', [PaymentController::class, 'cancelSubscription'])
            ->name('cancel-subscription')
            ->middleware('throttle:10,1');
    });

    // Orders
    Route::get('/orders', [PaymentController::class, 'getOrdersList'])->name('orders.index');
});
