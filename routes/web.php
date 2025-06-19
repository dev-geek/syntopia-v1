<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Admin\SubAdminController;
use App\Http\Controllers\SocialController;
use App\Models\User;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\UserLogController;
use App\Http\Controllers\Auth\AdminForgotPasswordController;
use App\Http\Controllers\Auth\AdminResetPasswordController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\VerificationTestController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentGatewaysController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Test routes (remove in production)
Route::get('/test-verification', function () {
    return view('verify-form');
});

// Test logging route
Route::get('/test-log', function () {
    // Test different log levels
    \Log::emergency('EMERGENCY: Test emergency message');
    \Log::alert('ALERT: Test alert message');
    \Log::critical('CRITICAL: Test critical message');
    \Log::error('ERROR: Test error message');
    \Log::warning('WARNING: Test warning message');
    \Log::notice('NOTICE: Test notice message');
    \Log::info('INFO: Test info message');
    \Log::debug('DEBUG: Test debug message');
    
    // Test writing to a custom log file
    \Log::channel('single')->info('Test message to single log file');
    
    // Check log file path
    $logPath = storage_path('logs/laravel.log');
    $logDir = dirname($logPath);
    $isWritable = is_writable($logDir) && (!file_exists($logPath) || is_writable($logPath));
    
    return response()->json([
        'status' => 'success',
        'log_file' => $logPath,
        'log_dir_writable' => is_writable($logDir) ? 'yes' : 'no',
        'log_file_writable' => !file_exists($logPath) ? 'n/a' : (is_writable($logPath) ? 'yes' : 'no'),
        'log_dir_exists' => file_exists($logDir) ? 'yes' : 'no',
        'log_file_exists' => file_exists($logPath) ? 'yes' : 'no',
        'message' => 'Check laravel.log for test messages'
    ]);
});

// Test payment logging
Route::get('/test-payment-log', function (\Illuminate\Http\Request $request) {
    // Test payment logging
    \Log::channel('payment')->info('=== PAYMENT LOG TEST ===');
    \Log::channel('payment')->info('Test payment log entry', [
        'time' => now()->toDateTimeString(),
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'test_data' => 'This is a test payment log entry'
    ]);
    
    return response()->json([
        'status' => 'success',
        'message' => 'Test payment log entry created',
        'log_file' => storage_path('logs/payment.log'),
        'log_dir_writable' => is_writable(storage_path('logs')) ? 'yes' : 'no',
        'log_file_exists' => file_exists(storage_path('logs/payment.log')) ? 'yes' : 'no'
    ]);
});

// Test payment callback endpoint
Route::get('/test-payment-callback', function (\Illuminate\Http\Request $request) {
    \Log::info('=== TEST PAYMENT CALLBACK RECEIVED ===');
    \Log::info('Request Method: ' . $request->method());
    \Log::info('Full URL: ' . $request->fullUrl());
    \Log::info('Request Headers: ', $request->headers->all());
    \Log::info('Request All Input: ', $request->all());
    \Log::info('Request Query Params: ', $request->query());
    \Log::info('Request IP: ' . $request->ip());
    \Log::info('User Agent: ' . $request->userAgent());
    \Log::info('Request Content: ' . $request->getContent());
    
    return response()->json([
        'status' => 'success',
        'message' => 'Test callback received',
        'data' => [
            'method' => $request->method(),
            'query_params' => $request->query(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toDateTimeString()
        ]
    ]);
})->name('payment.test-callback');
Route::get('/test-fastspring', [SubscriptionController::class, 'createFastSpringSession']);
Route::get('/test-paddle', [SubscriptionController::class, 'createPaddleSession']);
Route::get('/paddle-token', [SubscriptionController::class, 'getPaddleToken']);

// Guest routes (no authentication required)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login-user', [LoginController::class, 'customLogin'])->name('login.user');
    Route::get('/register', [App\Http\Controllers\Auth\RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [App\Http\Controllers\Auth\RegisterController::class, 'register'])->name('register.user');
    Route::get('/admin-login', [AdminController::class, 'login'])->name('admin-login');
    Route::get('/admin-register', [AdminController::class, 'register'])->name('admin-register');
});

// Admin Password Reset Routes (for guests)
Route::get('/admin/forgotpassword', [AdminController::class, 'AdminForgotPassword'])->name('admin.forgotpassword');
Route::get('/admin/password/reset', [AdminForgotPasswordController::class, 'showLinkRequestForm'])->name('admin.password.request');
Route::post('/admin/password/email', [AdminForgotPasswordController::class, 'sendResetLinkEmail'])->name('admin.password.email');
Route::post('/admin/password/reset', [AdminResetPasswordController::class, 'reset'])->name('admin.password.update');
Route::get('/admin/password/reset/{token}', [AdminResetPasswordController::class, 'showResetForm'])->name('admin.password.reset');

// Social Authentication Routes
Route::controller(SocialController::class)->group(function () {
    Route::get('auth/google', 'googleLogin')->name('auth.google');
    Route::get('auth/google-callback', 'googleAuthentication')->name('auth.google-callback');
    Route::get('login/facebook', 'redirectToFacebook')->name('login.facebook');
    Route::get('login/facebook/callback', 'handleFacebookCallback');
});

// Email Verification Routes (for authenticated but unverified users)
Route::middleware(['web'])->group(function () {
    Route::get('/email/verify', [VerificationController::class, 'show'])->name('verification.notice');
    Route::get('/verify-code', [VerificationController::class, 'show'])->name('verification.code');
    Route::post('/verify-code', [VerificationController::class, 'verifyCode'])->name('verify.code'); // Changed from 'verify' to 'verifyCode'
    Route::get('/resend-code', [VerificationController::class, 'resend'])->name('resend.code');
    Route::post('/email/verify', [VerificationController::class, 'verifyCode'])->name('verification.verify'); // Changed from 'verify' to 'verifyCode'
    Route::post('/email/resend', [VerificationController::class, 'resend'])->name('verification.resend');
});

// AJAX Routes
Route::post('/check-email', [LoginController::class, 'checkEmail']);

// Payment callback routes (no auth required)
Route::match(['get', 'post'], '/payment/success', [PaymentController::class, 'handleSuccess'])->name('payment.success');
Route::get('/payment/cancel', [PaymentController::class, 'handleCancel'])->name('payment.cancel');

// Protected Routes for VERIFIED USERS ONLY
Route::middleware(['auth', 'verified.custom'])->group(function () {
    // Main application routes
    Route::get('/', [SubscriptionController::class, 'index'])->name('home');

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'profile'])->name('user.profile');
    Route::post('/profile/update', [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::get('/update-password', [ProfileController::class, 'updatePassword'])->name('update-password');

    // Subscription routes
    Route::get('/select-sub', [SubscriptionController::class, 'selectSub'])->name('select-sub');
    Route::get('/confirm', [SubscriptionController::class, 'confirmSubscription'])->name('confirm');
    Route::get('/package/{package_name}', [ProfileController::class, 'package'])->name('package');
    Route::get('/pricing', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::get('/subscription', [SubscriptionController::class, 'handleSubscription'])->name('subscription.general');
    Route::get('/login-sub', [SubscriptionController::class, 'login'])->name('login-sub');

    // Package confirmation routes
    Route::get('/starter-package-confirmed', [SubscriptionController::class, 'starterPackageConfirmed'])->name('starter-package-confirmed');
    Route::get('/pro-package-confirmed', [SubscriptionController::class, 'proPackageConfirmed'])->name('pro-package-confirmed');
    Route::get('/business-package-confirmed', [SubscriptionController::class, 'businessPackageConfirmed'])->name('business-package-confirmed');

    // Orders
    Route::get('/orders', [App\Http\Controllers\OrderController::class, 'index'])->name('orders.index');
});

// Super Admin Only Routes
Route::middleware(['auth', 'role:Super Admin|Admin'])->group(function () {
    

    
});

// Route::middleware(['auth', 'verified.custom', 'role:User|Sub Admin|Super Admin'])->group(function () {
//     Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');
// });


// Keep the default Laravel auth routes but remove verify
Auth::routes(['verify' => false]);
