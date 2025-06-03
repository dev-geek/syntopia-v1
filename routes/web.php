<?php

use App\Http\Controllers\Admin\SubAdminController;
use Illuminate\Support\Facades\Route;
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

// Protected Routes for VERIFIED USERS ONLY
Route::middleware(['auth', 'verified.custom'])->group(function () {
    // Main application routes
    Route::get('/', [SubscriptionController::class, 'handleSubscription'])->name('home');
    Route::get('/home', [SubscriptionController::class, 'handleSubscription'])->name('profile');

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'profile'])->name('user.profile');
    Route::post('/profile/update', [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::get('/update-password', [ProfileController::class, 'updatePassword'])->name('update-password');

    // Subscription routes
    Route::get('/select-sub', [SubscriptionController::class, 'selectSub'])->name('select-sub');
    Route::get('/confirm', [SubscriptionController::class, 'confirmSubscription'])->name('confirm');
    Route::get('/package/{package_name}', [ProfileController::class, 'package'])->name('package');
    Route::get('/all-subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::get('/subscription', [SubscriptionController::class, 'handleSubscription'])->name('subscription.general');
    Route::get('/pricing', [SubscriptionController::class, 'pricing'])->name('pricing');
    Route::get('/login-sub', [SubscriptionController::class, 'login'])->name('login-sub');

    // Package confirmation routes
    Route::get('/starter-package-confirmed', [SubscriptionController::class, 'starterPackageConfirmed'])->name('starter-package-confirmed');
    Route::get('/pro-package-confirmed', [SubscriptionController::class, 'proPackageConfirmed'])->name('pro-package-confirmed');
    Route::get('/business-package-confirmed', [SubscriptionController::class, 'businessPackageConfirmed'])->name('business-package-confirmed');

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');

    // Payment routes
    Route::post('/payment/webhook/{gateway}', [SubscriptionController::class, 'handlePaymentWebhook'])->name('payment.webhook');
    // Route::post('/paddle-checkout/{package}', [PaymentController::class, 'paddleCheckout'])->name('paddle.checkout');
});

// Admin Routes (bypass verification for admins)
Route::middleware(['auth', 'role:Sub Admin|Super Admin'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
    Route::get('/admin-profile', [AdminController::class, 'profile'])->name('admin.profile');
    Route::get('/admin-orders', [AdminController::class, 'adminOrders'])->name('admin.orders');
    Route::get('/admin/user-logs', [UserLogController::class, 'index'])->name('users.logs');
    Route::delete('users/{user}', [AdminController::class, 'destroy'])->name('users.destroy');
});

// Super Admin Only Routes
Route::middleware(['auth', 'role:Super Admin|Admin'])->group(function () {
    Route::get('/manage-profile/{id}', [AdminController::class, 'manageProfile'])->name('manage.profile');
    Route::get('/manage-admin-profile/{id}', [AdminController::class, 'manageAdminProfile'])->name('manage.admin.profile');
    Route::post('/manage-profile/update/{id}', [AdminController::class, 'manageProfileUpdate'])->name('manage-profile.update');
    Route::post('/manage-admin-profile/update/{id}', [AdminController::class, 'manageAdminProfileUpdate'])->name('manage-admin-profile.update');

    Route::get('/add-users', [AdminController::class, 'addusers'])->name('add-users');
    Route::post('/add-user-excel', [AdminController::class, 'addExcelUsers'])->name('add-user-excel');
    Route::post('store-user', [AdminController::class, 'storeUser'])->name('store.user');

    // Sub Admin Management
    Route::get('/sub-admins', [AdminController::class, 'subadmins'])->name('subadmins');
    Route::get('sub-admins/create', [SubAdminController::class, 'create'])->name('sub-admins.create');
    Route::post('sub-admins', [SubAdminController::class, 'store'])->name('sub-admins.store');
    Route::get('sub-admins/{sub_admin}/edit', [SubAdminController::class, 'edit'])->name('sub-admins.edit');
    Route::put('sub-admins/{sub_admin}', [SubAdminController::class, 'update'])->name('sub-admins.update');
    Route::delete('sub-admins/{sub_admin}', [SubAdminController::class, 'destroy'])->name('sub-admins.destroy');

    // Payment Gateway Management
    Route::get('payment-gateways-list', [PaymentGatewaysController::class, 'index'])->name('payment-gateways.index');
    Route::post('/payment-gateways/toggle-status', [PaymentGatewaysController::class, 'toggleStatus'])->name('payment-gateways.toggleStatus');
});

// Keep the default Laravel auth routes but remove verify
Auth::routes(['verify' => false]);
