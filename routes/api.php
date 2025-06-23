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

// Public routes (no auth required)
Route::post('/payment/webhook/{gateway}', [SubscriptionController::class, 'handlePaymentWebhook'])
    ->name('api.payment.webhook');

Route::post('/webhooks/paddle', [PaymentController::class, 'handlePaddleWebhook'])
    ->name('webhooks.paddle');

Route::match(['get', 'post'], '/payments/success', [PaymentController::class, 'handleSuccess'])
    ->name('payments.success')
    ->middleware('web');

Route::get('/payments/paddle/verify', [PaymentController::class, 'verifyPaddlePayment'])
    ->name('payments.paddle.verify')
    ->middleware('web');

// PayProGlobal specific webhook handler
Route::post('/webhooks/payproglobal', [PaymentController::class, 'handlePayProGlobalWebhook'])
    ->name('webhooks.payproglobal');

// Authenticated API routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Payment routes
    Route::prefix('payments')->group(function () {
        Route::post('/paddle/checkout/{package}', [PaymentController::class, 'paddleCheckout'])
            ->name('payments.paddle.checkout');
        Route::post('/fastspring/checkout/{package}', [PaymentController::class, 'fastspringCheckout'])
            ->name('payments.fastspring.checkout');
        Route::post('/payproglobal/checkout/{package}', [PaymentController::class, 'payProGlobalCheckout'])
            ->name('payments.payproglobal.checkout');
        Route::post('/save-details', [PaymentController::class, 'savePaymentDetails'])
            ->name('payments.save-details');
        
        Route::get('/verify-payproglobal/{paymentReference}', [PaymentController::class, 'verifyPayProGlobalPaymentStatus'])
            ->name('payments.verify-payproglobal');
        
        Route::get('/verify-order/{orderId}', [PaymentController::class, 'verifyOrderStatus'])
            ->name('payments.verify-order');
    });

    Route::get('/orders', [PaymentController::class, 'getOrdersList'])
        ->name('orders.index');
});

// Cancel handler route
Route::get('/payments/cancel', [PaymentController::class, 'handleCancel'])
    ->name('payments.cancel')
    ->middleware('web');