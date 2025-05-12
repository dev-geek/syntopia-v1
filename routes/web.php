<?php

use App\Http\Controllers\Admin\SubAdminController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocialController;
use App\Models\User;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;
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
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/test-verification', function () {
    return view('verify-form'); // Your Blade view with the form
});
Route::get('/test-fastspring', [SubscriptionController::class, 'createFastSpringSession']);
Route::get('/test-paddle', [SubscriptionController::class, 'createPaddleSession']);
Route::get('/paddle-token', [SubscriptionController::class, 'getPaddleToken']);



Route::post('/verify-test-code', [VerificationTestController::class, 'verifyCode']);


Route::get('/login-sub', [SubscriptionController::class, 'login'])->name('login-sub');
Route::get('/admin/forgotpassword', [AdminController::class, 'AdminForgotPassword'])->name('admin.forgotpassword');
Route::get('/admin/password/reset', [AdminForgotPasswordController::class, 'showLinkRequestForm'])->name('admin.password.request');
Route::post('/admin/password/email', [AdminForgotPasswordController::class, 'sendResetLinkEmail'])->name('admin.password.email');
Route::post('/admin/password/reset', [AdminResetPasswordController::class, 'reset'])->name('admin.password.update');



Route::get('/admin/password/reset/{token}', [AdminResetPasswordController::class, 'showResetForm'])
    ->name('admin.password.reset');




Route::middleware(['auth'])->group(function () {
    Route::get('/select-sub', [SubscriptionController::class, 'selectSub'])->name('select-sub');
    Route::get('/confirm', [SubscriptionController::class, 'confirmSubscription'])->name('confirm');
    Route::get('/package/{package_name}', [ProfileController::class, 'package'])->name('package');
    Route::get('/profile', [ProfileController::class, 'profile'])->name('profile');
    Route::post('/profile/update', [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::get('/all-subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::get('/', [SubscriptionController::class, 'handleSubscription'])->name('profile');
    Route::get('/update-password', [ProfileController::class, 'updatePassword'])->name('update-password');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');


         //Subscription Plans
    Route::get('/subscription', [SubscriptionController::class, 'handleSubscription'])->name('subscription.general');
    Route::get('/pricing', [SubscriptionController::class, 'pricing'])->name('pricing');

    // Payment routes
    Route::post('/payment/webhook/{gateway}', [SubscriptionController::class, 'handlePaymentWebhook'])->name('payment.webhook');



    Route::get('/starter-package-confirmed', [SubscriptionController::class, 'starterPackageConfirmed'])->name('starter-package-confirmed');

    Route::get('/pro-package-confirmed', [SubscriptionController::class, 'proPackageConfirmed'])->name('pro-package-confirmed');

    Route::get('/business-package-confirmed', [SubscriptionController::class, 'businessPackageConfirmed'])->name('business-package-confirmed');


    });




// Add this line before your auth routes
Route::get('/login', [LoginController::class, 'showLoginForm'])
    ->name('login')
    ->middleware('guest');

//Admin Panel
Route::middleware(['check.login'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
    Route::get('/admin-profile', [AdminController::class, 'profile'])->name('admin.profile');
    Route::get('/admin-orders', [AdminController::class, 'adminOrders'])->name('admin.orders');
    Route::get('/admin/user-logs', [UserLogController::class, 'index'])->name('users.logs');
});

Route::middleware(['role'])->group(function () {
    Route::get('/manage-profile/{id}', [AdminController::class, 'manageProfile'])->name('manage.profile');
    Route::get('/manage-admin-profile/{id}', [AdminController::class, 'manageAdminProfile'])->name('manage.admin.profile');

    Route::post('/manage-profile/update/{id}', [AdminController::class, 'manageProfileUpdate'])->name('manage-profile.update');
    Route::post('/manage-admin-profile/update/{id}', [AdminController::class, 'manageAdminProfileUpdate'])->name('manage-admin-profile.update');

    Route::get('/add-users', [AdminController::class, 'addusers'])->name('add-users');
    Route::post('/add-user-excel', [AdminController::class, 'addExcelUsers'])->name('add-user-excel');
    Route::get('/sub-admins', [AdminController::class, 'subadmins'])->name('subadmins');
    // Route::post('/manage-sub-admins/update/{id}', [AdminController::class, 'managesubadmin'])->name('managesubadmin');

    Route::get('sub-admins/create', [SubAdminController::class, 'create'])->name('sub-admins.create');// Store new subadmin
    Route::post('sub-admins', [SubAdminController::class, 'store'])->name('sub-admins.store');
    Route::get('sub-admins/{sub_admin}/edit', [SubAdminController::class, 'edit'])->name('sub-admins.edit');
    Route::put('sub-admins/{sub_admin}', [SubAdminController::class, 'update'])->name('sub-admins.update');
    Route::delete('sub-admins/{sub_admin}', [SubAdminController::class, 'destroy'])->name('sub-admins.destroy');

    // Manage Payment Gateways
    Route::get('payment-gateways-list', [PaymentGatewaysController::class, 'index'])->name('payment-gateways.index');
    Route::post('/payment-gateways/toggle-status', [PaymentGatewaysController::class, 'toggleStatus'])->name('payment-gateways.toggleStatus');

});
Route::get('/admin-register', [AdminController::class, 'register'])->name('admin-register');

// Admin Login Route for non-authenticated users
Route::get('/admin-login', [AdminController::class, 'login'])->name('admin-login');

Route::post('/login-user', [LoginController::class, 'customLogin'])->name('login.user');

Route::controller(SocialController::class)->group(function () {
    Route::get('auth/google', 'googleLogin')->name('auth.google');
    Route::get('auth/google-callback', 'googleAuthentication')->name('auth.google-callback');
});

Auth::routes([
    'verify' => true,
]);

Route::get('/home', [SubscriptionController::class, 'handleSubscription'])->name('profile');


Route::get('login/facebook', [SocialController::class, 'redirectToFacebook'])->name('login.facebook');
Route::get('login/facebook/callback', [SocialController::class, 'handleFacebookCallback']);

Route::post('/check-email', [App\Http\Controllers\Auth\LoginController::class, 'checkEmail']);

// Registration Routes
Route::get('/register', [App\Http\Controllers\Auth\RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [App\Http\Controllers\Auth\RegisterController::class, 'register'])->name('register.user');

// Verification Routes
Route::middleware(['web'])->group(function () {
    Route::get('/verify-code', [App\Http\Controllers\Auth\VerificationController::class, 'show'])->name('verification.code');
    Route::post('/verify-code', [App\Http\Controllers\Auth\VerificationController::class, 'verify'])->name('verify.code');
    Route::get('/resend-code', [App\Http\Controllers\Auth\VerificationController::class, 'resend'])->name('resend.code');
});

// Add these routes if they don't exist
Route::get('/email/verify', function () {
    return view('auth.verify-code');
})->name('verification.notice');

Route::post('/email/verify', [App\Http\Controllers\Auth\VerificationController::class, 'verify'])
    ->name('verification.verify');

Route::post('/email/resend', [App\Http\Controllers\Auth\VerificationController::class, 'resend'])
    ->name('verification.resend');

Route::delete('users/{user}', [AdminController::class, 'destroy'])->name('users.destroy');
