<?php

use App\Http\Controllers\API\PaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/paddle/checkout/{package}', [PaymentController::class, 'paddleCheckout'])->name('paddle.checkout');
    Route::post('/fastspring/checkout/{packageName}', [PaymentController::class, 'fastspringCheckout']);
    Route::post('/payproglobal/checkout/{packageName}', [PaymentController::class, 'payProGlobalCheckout'])->name('payproglobal.checkout');
    Route::get('/payment/success', [PaymentController::class, 'handleSuccess'])->name('payment.success');
    Route::get('/payment/cancel', [PaymentController::class, 'handleCancel'])->name('payment.cancel');

    Route::post('/payment/save-details', [PaymentController::class, 'savePaymentDetails']);
    Route::post('/paddle/webhook', [PaymentController::class, 'handlePaddleWebhook'])->name('paddle.webhook');
});
