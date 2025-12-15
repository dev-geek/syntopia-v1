<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\AdminController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all authentication-related routes for your
| application. These routes handle login, registration, password reset,
| email verification, and social authentication.
|
*/

/*
|--------------------------------------------------------------------------
| Guest Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Login Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login/custom', [LoginController::class, 'customLogin'])->name('login.post');
    Route::post('/check-email', [LoginController::class, 'checkEmail'])->name('check-email');

    /*
    |--------------------------------------------------------------------------
    | Registration Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->name('register.user')->middleware('prevent.free.plan.abuse');

    /*
    |--------------------------------------------------------------------------
    | Admin Login & Registration Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/admin-login', [AdminController::class, 'login'])->name('admin-login');
    Route::post('/admin-login', [AdminController::class, 'adminLogin'])->name('admin.login');
    Route::get('/admin-register', [AdminController::class, 'register'])->name('admin-register');
    Route::post('/admin-register', [AdminController::class, 'adminRegister'])->name('admin.register');

    /*
    |--------------------------------------------------------------------------
    | Password Reset Routes (User Role)
    |--------------------------------------------------------------------------
    */
    Route::get('/password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/password/reset', [ResetPasswordController::class, 'reset'])->name('password.update');

    /*
    |--------------------------------------------------------------------------
    | Password Reset Routes (Admin Roles)
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->group(function () {
        Route::get('/forgotpassword', [ForgotPasswordController::class, 'showLinkRequestForm'])
            ->name('admin.password.request');
        Route::post('/password/check-email', [ForgotPasswordController::class, 'checkEmail'])
            ->name('admin.password.check-email');
        Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])
            ->name('admin.password.email');
        Route::get('/password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])
            ->name('admin.password.reset');
        Route::post('/password/reset', [ResetPasswordController::class, 'reset'])
            ->name('admin.password.update');
    });
});

/*
|--------------------------------------------------------------------------
| Social Authentication Routes (Public)
|--------------------------------------------------------------------------
*/
Route::controller(SocialController::class)->group(function () {
    Route::get('auth/google', 'googleLogin')->name('auth.google');
    Route::get('auth/google-callback', 'googleAuthentication')->name('auth.google-callback');
    Route::get('login/facebook', 'redirectToFacebook')->name('login.facebook');
    Route::get('login/facebook/callback', 'handleFacebookCallback')->name('auth.facebook-callback');
});

/*
|--------------------------------------------------------------------------
| Email Verification Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['web'])->group(function () {
    Route::get('/email/verify', [VerificationController::class, 'show'])->name('verification.notice');
    Route::post('/verify-code', [VerificationController::class, 'verifyCode'])->name('verification.verify');
    Route::post('/email/resend', [VerificationController::class, 'resend'])->name('verification.resend');
    Route::post('/verification/delete-user', [VerificationController::class, 'deleteUserAndRedirect'])->name('verification.deleteUserAndRedirect');
});

/*
|--------------------------------------------------------------------------
| Logout Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:Super Admin|Sub Admin'])->group(function () {
    Route::post('/admin-logout', [LoginController::class, 'logout'])->name('admin.logout');
});

/*
|--------------------------------------------------------------------------
| Laravel Auth Routes (customized)
|--------------------------------------------------------------------------
*/
Auth::routes(['verify' => false]);
