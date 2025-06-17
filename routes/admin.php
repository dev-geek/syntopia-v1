<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    AdminController,
    Dashboard\DashboardController,
    UserLogController,
    PaymentGatewaysController,
    SubAdminController,
};

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Here is where you can register admin routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group.
|
*/

// Admin routes
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:Super Admin|Sub Admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::get('/profile', [AdminController::class, 'profile'])->name('profile');
    Route::get('/orders', [AdminController::class, 'adminOrders'])->name('orders');
    Route::get('/user-logs', [UserLogController::class, 'index'])->name('users-logs');
    Route::delete('users/{user}', [AdminController::class, 'destroy'])->name('users.destroy');

    // Payment Gateway Management
    Route::get('payment-gateways-list', [PaymentGatewaysController::class, 'index'])->name('payment-gateways.index');
    Route::post('/payment-gateways/toggle-status', [PaymentGatewaysController::class, 'toggleStatus'])->name('payment-gateways.toggleStatus');

    // Sub Admin Management
    Route::get('/sub-admins', [AdminController::class, 'subadmins'])->name('subadmins');
    Route::get('sub-admins/create', [\App\Http\Controllers\Admin\SubAdminController::class, 'create'])->name('sub-admins.create');
    Route::post('sub-admins', [\App\Http\Controllers\Admin\SubAdminController::class, 'store'])->name('sub-admins.store');
    Route::get('sub-admins/{sub_admin}/edit', [\App\Http\Controllers\Admin\SubAdminController::class, 'edit'])->name('sub-admins.edit');
    Route::put('sub-admins/{sub_admin}', [\App\Http\Controllers\Admin\SubAdminController::class, 'update'])->name('sub-admins.update');
    Route::delete('sub-admins/{sub_admin}', [\App\Http\Controllers\Admin\SubAdminController::class, 'destroy'])->name('sub-admins.destroy');
});

// Regular user dashboard route
Route::prefix('user')->name('user.')->middleware(['auth', 'verified.custom', 'role:User'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');
});

// Root dashboard route that redirects based on role
Route::middleware(['auth', 'verified.custom'])->group(function () {
    Route::get('/dashboard', function () {
        if (auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Sub Admin')) {
            return redirect()->route('admin.users');
        }
        return redirect()->route('user.dashboard');
    })->name('dashboard.redirect');
});
