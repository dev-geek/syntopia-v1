<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\SocialController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Auth\AdminForgotPasswordController;
use App\Http\Controllers\Auth\AdminResetPasswordController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SoftwareAccessController;
use App\Models\Package;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Guest routes (no authentication required)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login/custom', [LoginController::class, 'customLogin'])->name('login.post');
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->name('register.user')->middleware('prevent.free.plan.abuse');
    Route::get('/admin-login', [AdminController::class, 'login'])->name('admin-login');
    Route::post('/admin-login', [AdminController::class, 'adminLogin'])->name('admin.login');
    Route::get('/admin-register', [AdminController::class, 'register'])->name('admin-register');
    Route::post('/admin-register', [AdminController::class, 'adminRegister'])->name('admin.register');
});

// Admin logout route
Route::post('/admin-logout', [LoginController::class, 'logout'])->name('admin.logout');

// Check admin password hash - REMOVE IN PRODUCTION
Route::get('/debug/check-admin-hash', function() {
    try {
        $user = \App\Models\User::where('email', 'admin@syntopia.io')->first();

        if (!$user) {
            return response()->json(['error' => 'Admin user not found'], 404);
        }

        $password = 'admin@syntopia.io';
        $isValid = \Illuminate\Support\Facades\Hash::check($password, $user->password);

        return response()->json([
            'user_id' => $user->id,
            'email' => $user->email,
            'password_hash' => $user->password,
            'password_match' => $isValid ? 'YES' : 'NO',
            'hash_info' => [
                'algorithm' => password_get_info($user->password)['algoName'] ?? 'unknown',
                'options' => password_get_info($user->password)['options'] ?? []
            ],
            'current_hash_of_test_password' => \Illuminate\Support\Facades\Hash::make($password),
            'status' => $user->status,
            'is_verified' => (bool)$user->email_verified_at,
            'has_admin_role' => $user->hasRole('Super Admin')
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Temporary admin password reset - REMOVE IN PRODUCTION
Route::get('/debug/reset-admin-password', function() {
    try {
        $user = \App\Models\User::where('email', 'admin@syntopia.io')->first();

        if (!$user) {
            $user = \App\Models\User::create([
                'name' => 'Super Admin',
                'email' => 'admin@syntopia.io',
                'password' => 'admin@syntopia.io', // Will be hashed by model's setPasswordAttribute
                'status' => 1,
                'email_verified_at' => now(),
                'city' => 'Lahore',
                'pet' => 'Dog'
            ]);
            $user->assignRole('Super Admin');
        }

        // Force reset password
        $user->password = 'admin@syntopia.io';
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Admin password has been reset to: admin@syntopia.io',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'has_admin_role' => $user->hasRole('Super Admin'),
                'email_verified' => (bool)$user->email_verified_at
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Temporary debug route - REMOVE IN PRODUCTION
Route::get('/debug/admin-check', function() {
    try {
        $user = \App\Models\User::where('email', 'like', '%admin%')
            ->orWhereHas('roles', function($q) {
                $q->whereIn('name', ['Super Admin', 'Sub Admin']);
            })
            ->first();

        if ($user) {
            return response()->json([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'has_admin_role' => $user->hasRole(['Super Admin', 'Sub Admin']),
                'password_set' => !empty($user->password),
                'email_verified' => (bool)$user->email_verified_at
            ]);
        }
        return response()->json(['error' => 'No admin user found'], 404);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Admin Password Reset Routes
Route::prefix('admin')->group(function () {
    // Admin Password Reset Routes
    Route::get('/forgotpassword', [\App\Http\Controllers\Auth\AdminForgotPasswordController::class, 'showLinkRequestForm'])
        ->name('admin.password.request');

    Route::post('/password/check-email', [\App\Http\Controllers\Auth\AdminForgotPasswordController::class, 'checkEmail'])
        ->name('admin.password.check-email');

    Route::post('/password/email', [\App\Http\Controllers\Auth\AdminForgotPasswordController::class, 'sendResetLinkEmail'])
        ->name('admin.password.email');

    Route::get('/password/reset/{token}', [\App\Http\Controllers\Auth\AdminResetPasswordController::class, 'showResetForm'])
        ->name('admin.password.reset');

    Route::post('/password/reset', [\App\Http\Controllers\Auth\AdminResetPasswordController::class, 'reset'])
        ->name('admin.password.update');
});

// Social Authentication Routes
Route::controller(SocialController::class)->group(function () {
    Route::get('auth/google', 'googleLogin')->name('auth.google');
    Route::get('auth/google-callback', 'googleAuthentication')->name('auth.google-callback');
    Route::get('login/facebook', 'redirectToFacebook')->name('login.facebook');
    Route::get('login/facebook/callback', 'handleFacebookCallback')->name('auth.facebook-callback');
});

// Public routes
Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription');
Route::post('/check-email', [LoginController::class, 'checkEmail'])->name('check-email');

// Payment callback routes
Route::match(['get', 'post'], '/payments/success', [PaymentController::class, 'handleSuccess'])->name('payments.success');
Route::match(['get', 'post'], '/payments/addon-success', [PaymentController::class, 'handleAddonSuccess'])->name('payments.addon-success');
Route::post('/payments/addon-debug-log', [PaymentController::class, 'addonDebugLog'])->name('payments.addon-debug-log');
Route::get('/payments/cancel', [PaymentController::class, 'handleCancel'])->name('payments.cancel');
Route::get('/payments/popup-cancel', [PaymentController::class, 'handlePopupCancel'])->name('payments.popup-cancel');
Route::get('/payments/license-error', [PaymentController::class, 'handleLicenseError'])->name('payments.license-error');

// Subscription cancellation route (web-based)
Route::post('/payments/cancel-subscription', [PaymentController::class, 'cancelSubscription'])
    ->name('payments.cancel-subscription')
    ->middleware(['auth', 'verified.custom']);

// Email Verification Routes
Route::middleware(['web'])->group(function () {
    Route::get('/email/verify', [VerificationController::class, 'show'])->name('verification.notice');
    Route::post('/verify-code', [VerificationController::class, 'verifyCode'])->name('verification.verify');
    Route::post('/email/resend', [VerificationController::class, 'resend'])->name('verification.resend');
    Route::post('/verification/delete-user', [VerificationController::class, 'deleteUserAndRedirect'])->name('verification.deleteUserAndRedirect');
});

Route::middleware(['auth', 'verified.custom'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('user.dashboard');

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'profile'])->name('user.profile');
    Route::post('/profile/update', [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::get('/update-password', [ProfileController::class, 'updatePassword'])->name('update-password');

    // Subscription routes
    Route::get('/user/subscription-details', [SubscriptionController::class, 'subscriptionDetails'])->name('user.subscription.details');
    Route::get('/subscription/upgrade', [SubscriptionController::class, 'upgrade'])->name('subscription.upgrade');
    Route::get('/subscription/downgrade', [SubscriptionController::class, 'downgrade'])->name('subscription.downgrade');

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');

    // Software access routes
    Route::get('/software/access', [SoftwareAccessController::class, 'redirectToSoftware'])->name('software.access');
    Route::post('/software/token', [SoftwareAccessController::class, 'generateAccessToken'])->name('software.token');
});

// Laravel Auth Routes (customized)
Auth::routes(['verify' => false]);

// Custom Password Reset Routes
Route::get('/password/reset', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('/password/email', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::post('/password/reset', [App\Http\Controllers\Auth\ResetPasswordController::class, 'reset'])->name('password.update');
Route::get('/password/reset/{token}', [App\Http\Controllers\Auth\ResetPasswordController::class, 'showResetForm'])->name('password.reset');

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
