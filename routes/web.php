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
    Route::post('/login', [LoginController::class, 'customLogin'])->name('login.user');
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->name('register.user');
    Route::get('/admin-login', [AdminController::class, 'login'])->name('admin-login');
    Route::post('/admin-login', [AdminController::class, 'adminLogin'])->name('admin.login');
    Route::get('/admin-register', [AdminController::class, 'register'])->name('admin-register');
});

// Admin logout route
Route::post('/admin-logout', [LoginController::class, 'logout'])->name('admin.logout');

// Admin Password Reset Routes
Route::prefix('admin')->group(function () {
    Route::get('/forgotpassword', [AdminController::class, 'AdminForgotPassword'])->name('admin.forgotpassword');
    Route::get('/password/reset', [AdminForgotPasswordController::class, 'showLinkRequestForm'])->name('admin.password.request');
    Route::post('/password/email', [AdminForgotPasswordController::class, 'sendResetLinkEmail'])->name('admin.password.email');
    Route::post('/password/reset', [AdminResetPasswordController::class, 'reset'])->name('admin.password.update');
    Route::get('/password/reset/{token}', [AdminResetPasswordController::class, 'showResetForm'])->name('admin.password.reset');
});

// Social Authentication Routes
Route::controller(SocialController::class)->group(function () {
    Route::get('auth/google', 'googleLogin')->name('auth.google');
    Route::get('auth/google-callback', 'googleAuthentication')->name('auth.google-callback');
    Route::get('login/facebook', 'redirectToFacebook')->name('login.facebook');
    Route::get('login/facebook/callback', 'handleFacebookCallback')->name('auth.facebook-callback');
});

// Public routes
Route::get('/pricing', [SubscriptionController::class, 'index'])->name('pricing');
Route::post('/check-email', [LoginController::class, 'checkEmail'])->name('check-email');

// Payment callback routes
Route::match(['get', 'post'], '/payments/success', [PaymentController::class, 'handleSuccess'])->name('payments.success');
Route::get('/payments/cancel', [PaymentController::class, 'handleCancel'])->name('payments.cancel');
Route::get('/payments/popup-cancel', [PaymentController::class, 'handlePopupCancel'])->name('payments.popup-cancel');

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

// Protected Routes (Authenticated and Verified Users)
Route::middleware(['auth', 'verified.custom'])->group(function () {
    Route::get('/', [SubscriptionController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('user.dashboard');

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'profile'])->name('user.profile');
    Route::post('/profile/update', [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::get('/update-password', [ProfileController::class, 'updatePassword'])->name('update-password');

    // Subscription routes
    Route::get('/user/subscription-details', [SubscriptionController::class, 'subscriptionDetails'])->name('user.subscription.details');

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
});

// Laravel Auth Routes (customized)
Auth::routes(['verify' => false]);
