<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\AdminForgotPasswordController;
use App\Http\Controllers\Auth\AdminResetPasswordController;
use App\Http\Controllers\SocialController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SoftwareAccessController;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Routes are organized by priority and functionality for optimal performance.
| Most frequently accessed routes are placed first.
|--------------------------------------------------------------------------
*/

// Root redirect (highest priority)
Route::get('/', fn() => redirect()->route('login'))->name('home');

// Public routes (no authentication required)
Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription');
Route::post('/check-email', [LoginController::class, 'checkEmail'])->name('check-email');

// Payment callback routes (public, frequently accessed)
Route::match(['get', 'post'], '/payments/success', [PaymentController::class, 'handleSuccess'])->name('payments.success');
Route::match(['get', 'post'], '/payments/addon-success', [PaymentController::class, 'handleAddonSuccess'])->name('payments.addon-success');
Route::get('/payments/cancel', [PaymentController::class, 'handleCancel'])->name('payments.cancel');
Route::match(['get', 'post'], '/payments/popup-cancel', [PaymentController::class, 'handlePopupCancel'])->name('payments.popup-cancel');
Route::get('/payments/license-error', [PaymentController::class, 'handleLicenseError'])->name('payments.license-error');
Route::post('/payments/addon-debug-log', [PaymentController::class, 'addonDebugLog'])->name('payments.addon-debug-log');

// Guest routes (authentication pages)
Route::middleware('guest')->group(function () {
    // User authentication
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login/custom', [LoginController::class, 'customLogin'])->name('login.post');
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])
        ->name('register.user')
        ->middleware('prevent.free.plan.abuse');

    // Admin authentication
    Route::get('/admin-login', [AdminController::class, 'login'])->name('admin-login');
    Route::post('/admin-login', [AdminController::class, 'adminLogin'])->name('admin.login');
    Route::get('/admin-register', [AdminController::class, 'register'])->name('admin-register');
    Route::post('/admin-register', [AdminController::class, 'adminRegister'])->name('admin.register');
});

// Social authentication (public, no auth required)
Route::controller(SocialController::class)->group(function () {
    Route::get('/auth/google', 'googleLogin')->name('auth.google');
    Route::get('/auth/google-callback', 'googleAuthentication')->name('auth.google-callback');
    Route::get('/login/facebook', 'redirectToFacebook')->name('login.facebook');
    Route::get('/login/facebook/callback', 'handleFacebookCallback')->name('auth.facebook-callback');
});

// Email verification routes
Route::middleware('web')->group(function () {
    Route::get('/email/verify', [VerificationController::class, 'show'])->name('verification.notice');
    Route::post('/verify-code', [VerificationController::class, 'verifyCode'])->name('verification.verify');
    Route::post('/email/resend', [VerificationController::class, 'resend'])->name('verification.resend');
    Route::post('/verification/delete-user', [VerificationController::class, 'deleteUserAndRedirect'])
        ->name('verification.deleteUserAndRedirect');
});

// Password reset routes (public)
Route::get('/password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('/password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('/password/reset', [ResetPasswordController::class, 'reset'])->name('password.update');

// Admin password reset routes
Route::prefix('admin')->group(function () {
    Route::get('/forgotpassword', [AdminForgotPasswordController::class, 'showLinkRequestForm'])
        ->name('admin.password.request');
    Route::post('/password/check-email', [AdminForgotPasswordController::class, 'checkEmail'])
        ->name('admin.password.check-email');
    Route::post('/password/email', [AdminForgotPasswordController::class, 'sendResetLinkEmail'])
        ->name('admin.password.email');
    Route::get('/password/reset/{token}', [AdminResetPasswordController::class, 'showResetForm'])
        ->name('admin.password.reset');
    Route::post('/password/reset', [AdminResetPasswordController::class, 'reset'])
        ->name('admin.password.update');
});

// Authenticated user routes
Route::middleware(['auth', 'verified.custom'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('user.dashboard');

    // Profile management
    Route::get('/profile', [ProfileController::class, 'profile'])->name('user.profile');
    Route::post('/profile/update', [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::get('/update-password', [ProfileController::class, 'updatePassword'])->name('update-password');

    // Subscription management
    Route::match(['get', 'post'], '/user/subscription-details', [SubscriptionController::class, 'subscriptionDetails'])
        ->name('user.subscription.details');
    Route::get('/subscription/upgrade', [SubscriptionController::class, 'upgrade'])->name('subscription.upgrade');
    Route::get('/subscription/downgrade', [SubscriptionController::class, 'downgrade'])->name('subscription.downgrade');
    Route::post('/payments/cancel-subscription', [PaymentController::class, 'cancelSubscription'])
        ->name('payments.cancel-subscription');

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');

    // Software access
    Route::get('/software/access', [SoftwareAccessController::class, 'redirectToSoftware'])->name('software.access');
    Route::post('/software/token', [SoftwareAccessController::class, 'generateAccessToken'])->name('software.token');
});

// Admin logout (no middleware group needed)
Route::post('/admin-logout', [LoginController::class, 'logout'])->name('admin.logout');

// Error handling routes
Route::get('/access-denied', function () {
    $exceptionMessage = session('exception_message', 'This action is unauthorized.');
    $exception = new HttpException(403, $exceptionMessage);

    return response()->view('errors.403', ['exception' => $exception], 403);
})->name('access-denied');

// Laravel default auth routes (disabled verify)
Auth::routes(['verify' => false]);
