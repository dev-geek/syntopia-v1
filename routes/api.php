<?php

use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public webhook routes (no auth required)
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    // Generic webhook handler
    Route::post('/{gateway}', [PaymentController::class, 'handleWebhookWithQueue'])
        ->name('payment')
        ->where('gateway', 'paddle|payproglobal|fastspring');

    // Legacy webhook routes (for backward compatibility)
    Route::post('/paddle', [PaymentController::class, 'handlePaddleWebhook'])
        ->name('paddle');
    Route::post('/payproglobal', [PaymentController::class, 'handlePayProGlobalWebhook'])
        ->name('payproglobal');
    Route::post('/fastspring', [PaymentController::class, 'handleFastSpringWebhook'])
        ->name('fastspring');
});

// Public success/cancel handlers
Route::middleware('web')->group(function () {
    Route::match(['get', 'post'], '/payments/success', [PaymentController::class, 'handleSuccess'])
        ->name('payments.success');
    Route::get('/payments/cancel', [PaymentController::class, 'handleCancel'])
        ->name('payments.cancel');
    Route::get('/payments/paddle/verify', [PaymentController::class, 'verifyPaddlePayment'])
        ->name('payments.paddle.verify');
});

// Authenticated API routes
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    // User info
    Route::get('/user', function (Request $request) {
        return $request->user()->load(['package', 'paymentGateway']);
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

        // Payment verification and management
        Route::get('/verify-payproglobal/{paymentReference}', [PaymentController::class, 'verifyPayProGlobalPaymentStatus'])
            ->name('verify-payproglobal');

        Route::get('/verify-order/{orderId}', [PaymentController::class, 'verifyOrderStatus'])
            ->name('verify-order');

        Route::post('/save-details', [PaymentController::class, 'savePaymentDetails'])
            ->name('save-details');

        Route::get('/popup-cancel', [PaymentController::class, 'handlePopupCancel'])->name('popup-cancel');
    });

    // Orders management
    Route::get('/orders', [PaymentController::class, 'getOrdersList'])
        ->name('orders.index');
});
