<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\{
    Dashboard\DashboardController,
    ProfileController,
    SubscriptionController,
    OrderController,
    SoftwareAccessController,
    Payments\PaymentController,
    API\LicenseController,
    API\FreePlanController,
};

/*
|--------------------------------------------------------------------------
| User Role Routes
|--------------------------------------------------------------------------
|
| Here is where you can register user routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group.
|
*/

/*
|--------------------------------------------------------------------------
| User Web Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified.custom', 'role:User'])->group(function () {
    // Dashboard
    Route::match(['get', 'post'], '/dashboard', [DashboardController::class, 'dashboard'])->name('user.dashboard');

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'profile'])->name('user.profile');
    Route::post('/profile/update', [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::get('/update-password', [ProfileController::class, 'updatePassword'])->name('update-password');

    // Subscription routes
    Route::match(['get', 'post'], '/user/subscription-details', [SubscriptionController::class, 'subscriptionDetails'])->name('user.subscription.details');
    Route::get('/subscription/upgrade', [SubscriptionController::class, 'upgrade'])->name('subscription.upgrade');
    Route::get('/subscription/downgrade', [SubscriptionController::class, 'downgrade'])->name('subscription.downgrade');

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');

    // Software access routes
    Route::get('/software/access', [SoftwareAccessController::class, 'redirectToSoftware'])->name('software.access');
    Route::post('/software/token', [SoftwareAccessController::class, 'generateAccessToken'])->name('software.token');

    // Subscription cancellation
    Route::post('/payments/cancel-subscription', [PaymentController::class, 'cancelSubscription'])
        ->name('payments.cancel-subscription');
});

/*
|--------------------------------------------------------------------------
| User API Routes (Sanctum Authentication)
|--------------------------------------------------------------------------
*/
Route::prefix('api')->middleware(['auth:sanctum', 'throttle:60,1', 'role:User'])->group(function () {
    // User info
    Route::get('/user', function (Request $request) {
        return $request->user()->load(['package', 'paymentGateway']);
    })->name('user.info');

    // Payment routes
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::post('/{gateway}/checkout/{package}', [PaymentController::class, 'gatewayCheckout'])
            ->name('gateway.checkout')
            ->middleware('throttle:10,1');

        // Route::post('/payproglobal/checkout/{package}', [PaymentController::class, 'payProGlobalCheckout'])
        //     ->name('payproglobal.checkout')
        //     ->middleware('throttle:10,1');

        // Route::get('/verify-payproglobal/{paymentReference}', [PaymentController::class, 'verifyPayProGlobalPaymentStatus'])
        //     ->name('verify-payproglobal');

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
            ->name('api.cancel-subscription')
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
    Route::get('/orders', [PaymentController::class, 'getOrdersList'])->name('api.orders.index');

    // License management
    Route::prefix('licenses')->name('licenses.')->group(function () {
        Route::get('/current', [LicenseController::class, 'getCurrentLicense'])->name('current');
        Route::get('/history', [LicenseController::class, 'getLicenseHistory'])->name('history');
        Route::post('/activate/{licenseId}', [LicenseController::class, 'activateLicense'])->name('activate');
        Route::get('/check-access/{packageName}', [LicenseController::class, 'checkPackageAccess'])->name('check-access');
    });

    // Free Plan Management Routes
    Route::prefix('free-plan')->name('free-plan.')->group(function () {
        Route::get('/eligibility', [FreePlanController::class, 'checkEligibility'])->name('eligibility');
        Route::post('/assign', [FreePlanController::class, 'assignFreePlan'])->name('assign');
        Route::get('/status', [FreePlanController::class, 'getStatus'])->name('status');
        Route::post('/report-suspicious', [FreePlanController::class, 'reportSuspiciousActivity'])->name('report-suspicious');
    });
});
