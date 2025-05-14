<?php

use App\Http\Controllers\API\PaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/paddle/checkout/{package}', [PaymentController::class, 'paddleCheckout']);
    Route::post('/fastspring/checkout/{package}', [PaymentController::class, 'fastspringCheckout']);
    Route::post('/payproglobal/checkout/{package}', [PaymentController::class, 'payProGlobalCheckout']);
    Route::get('/payment/success', [PaymentController::class, 'handleSuccess'])->name('payment.success');
    Route::get('/payment/cancel', [PaymentController::class, 'handleCancel'])->name('payment.cancel');
});
