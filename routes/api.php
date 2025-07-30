<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\TokenDecryptionController;
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

// PayProGlobal webhook (direct route for external calls)
Route::post('/payproglobal', [PaymentController::class, 'handlePayProGlobalWebhook'])->name('payproglobal.webhook');

// Test endpoint
Route::post('/payproglobal/test', [PaymentController::class, 'testPayProGlobalWebhook'])->name('payproglobal.test');

// Token decryption routes (for software auto-login)
Route::prefix('token')->name('token.')->group(function () {
    Route::match(['POST', 'OPTIONS'], '/decrypt', [TokenDecryptionController::class, 'decryptToken'])->name('decrypt');
    Route::match(['POST', 'OPTIONS'], '/validate', [TokenDecryptionController::class, 'validateToken'])->name('validate');
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

        Route::post('/upgrade/{package}', [PaymentController::class, 'upgradeToPackage'])
            ->name('upgrade.package')
            ->middleware('throttle:10,1');

        Route::post('/downgrade', [PaymentController::class, 'downgradeSubscription'])
            ->name('downgrade')
            ->middleware('throttle:10,1');

        Route::post('/cancel', [PaymentController::class, 'cancelSubscription'])
            ->name('cancel-subscription')
            ->middleware('throttle:10,1');

        Route::get('/paddle/success', [PaymentController::class, 'paddleSuccess'])->name('paddle.success');
        Route::get('/verify-order/{transactionId}', [PaymentController::class, 'verifyOrder'])->name('verify-order');
        Route::get('/payproglobal/latest-order', [PaymentController::class, 'getLatestPayProGlobalOrder'])
            ->name('payproglobal.latest-order');

        // Test endpoint for debugging
        Route::get('/test-paddle-config', [PaymentController::class, 'testPaddleConfiguration'])
            ->name('test.paddle-config');
    });

    // Orders
    Route::get('/orders', [PaymentController::class, 'getOrdersList'])->name('orders.index');

    // License management
    Route::prefix('licenses')->name('licenses.')->group(function () {
        Route::get('/current', [\App\Http\Controllers\API\LicenseController::class, 'getCurrentLicense'])->name('current');
        Route::get('/history', [\App\Http\Controllers\API\LicenseController::class, 'getLicenseHistory'])->name('history');
        Route::post('/activate/{licenseId}', [\App\Http\Controllers\API\LicenseController::class, 'activateLicense'])->name('activate');
        Route::get('/check-access/{packageName}', [\App\Http\Controllers\API\LicenseController::class, 'checkPackageAccess'])->name('check-access');
    });
});
