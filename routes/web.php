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

// Root route - handle PayProGlobal POST callbacks or redirect to dashboard
Route::match(['get', 'post'], '/', function (Request $request) {
    // Handle PayProGlobal POST callbacks - forward to payment success handler
    if ($request->isMethod('post') && ($request->has('ORDER_STATUS') || $request->has('ORDER_ID') || $request->has('ORDER_ITEMS'))) {
        \Illuminate\Support\Facades\Log::info('PayProGlobal POST callback detected in root route, forwarding to payments.success handler', [
            'has_order_status' => $request->has('ORDER_STATUS'),
            'has_order_id' => $request->has('ORDER_ID'),
            'order_status' => $request->input('ORDER_STATUS'),
        ]);

        $paymentController = app(\App\Http\Controllers\Payments\PaymentController::class);
        $request->merge(['gateway' => 'payproglobal']);
        return $paymentController->handleSuccess($request);
    }

    // For GET requests or non-PayProGlobal POST requests, redirect to dashboard
    return redirect()->route('user.dashboard');
});

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription');

/*
|--------------------------------------------------------------------------
| Payment Callback Routes (Public - No Authentication Required)
|--------------------------------------------------------------------------
*/
Route::match(['get', 'post'], '/payments/success', [PaymentController::class, 'handleSuccess'])->name('payments.success');
Route::match(['get', 'post'], '/payments/addon-success', [PaymentController::class, 'handleAddonSuccess'])->name('payments.addon-success');
Route::post('/payments/addon-debug-log', [PaymentController::class, 'addonDebugLog'])->name('payments.addon-debug-log');
Route::get('/payments/cancel', [PaymentController::class, 'handleCancel'])->name('payments.cancel');
Route::get('/payments/popup-cancel', [PaymentController::class, 'handlePopupCancel'])->name('payments.popup-cancel');
Route::get('/payments/license-error', [PaymentController::class, 'handleLicenseError'])->name('payments.license-error');

// PayProGlobal POST callback handler (outside auth middleware to handle unauthenticated callbacks)
Route::post('/user/dashboard', function (Request $request) {
    if ($request->has('ORDER_STATUS') || $request->has('ORDER_ID') || $request->has('ORDER_ITEMS')) {
        \Illuminate\Support\Facades\Log::info('PayProGlobal POST callback detected in dashboard route (outside auth), forwarding to payments.success handler', [
            'has_order_status' => $request->has('ORDER_STATUS'),
            'has_order_id' => $request->has('ORDER_ID'),
            'order_status' => $request->input('ORDER_STATUS'),
        ]);

        $paymentController = app(\App\Http\Controllers\Payments\PaymentController::class);
        $request->merge(['gateway' => 'payproglobal']);
        return $paymentController->handleSuccess($request);
    }

    // If not a PayProGlobal callback, redirect to login
    return redirect()->route('login');
})->name('user.dashboard.post');

/*
|--------------------------------------------------------------------------
| Public Webhook Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/
Route::prefix('api/webhooks')->name('webhooks.')->group(function () {
    Route::post('/paddle', [PaymentController::class, 'handlePaddleWebhook'])->name('paddle');
    Route::post('/fastspring', [PaymentController::class, 'handleFastSpringWebhook'])->name('fastspring');
    Route::post('/payproglobal', [PaymentController::class, 'handlePayProGlobalWebhook'])->name('payproglobal');
});

// PayProGlobal webhook (direct route for external calls)
Route::post('/api/payproglobal', [PaymentController::class, 'handlePayProGlobalWebhook'])->name('payproglobal.webhook');

// Token decryption routes (for software auto-login - public)
Route::prefix('api/token')->name('token.')->group(function () {
    Route::match(['POST', 'OPTIONS'], '/decrypt', [\App\Http\Controllers\API\TokenDecryptionController::class, 'decryptToken'])->name('decrypt');
    Route::match(['POST', 'OPTIONS'], '/validate', [\App\Http\Controllers\API\TokenDecryptionController::class, 'validateToken'])->name('validate');
});

/*
|--------------------------------------------------------------------------
| Super Admin Only Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:Super Admin'])->group(function () {
    // Super Admin specific routes can be added here
    // Most Super Admin routes are in routes/admin.php
});

/*
|--------------------------------------------------------------------------
| Super Admin and Sub Admin Routes (Admin Routes)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:Super Admin|Sub Admin'])->group(function () {
    // Most admin routes are in routes/admin.php
});

/*
|--------------------------------------------------------------------------
| User Role Routes
|--------------------------------------------------------------------------
| User routes are now in routes/user.php
*/

// Test endpoint
Route::post('/payproglobal/test', [PaymentController::class, 'testPayProGlobalWebhook'])->name('payproglobal.test');

// Demo route for software login testing
Route::get('/software-login-demo', function() {
    return view('software-login-demo');
})->name('software.login.demo');

// Test token decryption route
Route::get('/test-token/{token}', function($token) {
    try {
        $decryptedData = \Illuminate\Support\Facades\Crypt::decryptString($token);
        $tokenData = json_decode($decryptedData, true);

        return response()->json([
            'success' => true,
            'token_data' => [
                'user_id' => $tokenData['user_id'] ?? 'N/A',
                'email' => $tokenData['email'] ?? 'N/A',
                'expires_at' => $tokenData['expires_at'] ?? 'N/A',
                'is_expired' => \Carbon\Carbon::now()->timestamp > ($tokenData['expires_at'] ?? 0)
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
})->name('test.token');

Route::get('/cron-status', [AdminController::class, 'cronStatus'])->name('cron.status');
