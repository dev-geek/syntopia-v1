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

// Public API routes (no auth required)
Route::post('/payment/webhook/{gateway}', [SubscriptionController::class, 'handlePaymentWebhook'])
    ->name('api.payment.webhook');

// Payment success callback (public)
Route::match(['get', 'post'], '/payments/success', [PaymentController::class, 'handleSuccess'])
    ->name('payments.success');

// Authenticated API routes
Route::middleware('auth:sanctum')->group(function () {
    // User information
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Payment routes
    Route::prefix('payments')->group(function () {
        Route::post('/paddle/checkout/{package}', [PaymentController::class, 'paddleCheckout'])->name('payments.paddle.checkout');
        Route::post('/fastspring/checkout/{package}', [PaymentController::class, 'fastspringCheckout'])->name('payments.fastspring.checkout');
        Route::post('/payproglobal/checkout/{package}', [PaymentController::class, 'payProGlobalCheckout'])->name('payments.payproglobal.checkout');
        Route::post('/save-details', [PaymentController::class, 'savePaymentDetails'])->name('payments.save-details');
        Route::get('/verify-payproglobal/{paymentId}', [PaymentController::class, 'verifyPayProGlobalPaymentStatus'])->name('payments.verify-payproglobal');
    });

    // Order routes
    Route::get('/orders', [PaymentController::class, 'getOrdersList'])->name('orders.index');
});
