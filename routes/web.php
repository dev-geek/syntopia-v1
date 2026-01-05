<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Payments\PaymentController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Root Route - Redirect to Login
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription');
Route::post('/public/submit', function (Request $request) {
    // Handle public POST request
    return response()->json(['message' => 'Public POST route received', 'data' => $request->all()]);
    // dd the user id and order id
    dd($request->user()->id, $request->order_id);
})->name('public.submit');

/*
|--------------------------------------------------------------------------
| Payment Callback Routes (Public - No Authentication Required)
|--------------------------------------------------------------------------
*/
Route::match(['get', 'post'], '/payments/success', [PaymentController::class, 'handleSuccess'])->name('payments.success');
Route::match(['get', 'post'], '/payments/addon-success', [PaymentController::class, 'handleAddonSuccess'])->name('payments.addon-success');
Route::get('/payments/payproglobal-thankyou', [PaymentController::class, 'handlePayProGlobalThankYou'])->name('payments.payproglobal-thankyou');

// Token decryption routes (for software auto-login - public)
Route::prefix('api/token')->name('token.')->group(function () {
    Route::match(['POST', 'OPTIONS'], '/decrypt', [\App\Http\Controllers\API\TokenDecryptionController::class, 'decryptToken'])->name('decrypt');
    Route::match(['POST', 'OPTIONS'], '/validate', [\App\Http\Controllers\API\TokenDecryptionController::class, 'validateToken'])->name('validate');
});

/*
|--------------------------------------------------------------------------
| User Role Routes
|--------------------------------------------------------------------------
| User routes are now in routes/user.php
*/

Route::get('/cron-status', [AdminController::class, 'cronStatus'])->name('cron.status');
