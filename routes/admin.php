<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    AdminController,
    Dashboard\DashboardController,
    UserLogController,
    PaymentGatewaysController,
    ProfileController,
    Admin\SubAdminController,
    Admin\FreePlanAttemptController,
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

// Admin routes (Super Admin and Sub Admin)
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:Super Admin|Sub Admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::get('/profile', [AdminController::class, 'profile'])->name('profile');
    Route::put('/profile/update', [AdminController::class, 'updateProfile'])->name('profile.update');
    Route::get('/orders', [AdminController::class, 'adminOrders'])->name('orders');
    Route::get('/user-logs', [UserLogController::class, 'index'])->name('users-logs');
    Route::get('/run-scheduler', [AdminController::class, 'runScheduler'])->name('run-scheduler');
    Route::delete('users/{user}', [AdminController::class, 'destroy'])->name('users.destroy');

    // Payment Gateway Management
    Route::get('payment-gateways-list', [PaymentGatewaysController::class, 'index'])->name('payment-gateways.index');
    Route::post('/payment-gateways/toggle-status', [PaymentGatewaysController::class, 'toggleStatus'])->name('payment-gateways.toggleStatus');


    Route::get('/manage-profile/{id}', [AdminController::class, 'manageProfile'])->name('manage.profile');
    Route::get('/manage-admin-profile/{id}', [AdminController::class, 'manageAdminProfile'])->name('manage.admin.profile');
    Route::post('/manage-profile/update/{id}', [AdminController::class, 'manageProfileUpdate'])->name('manage-profile.update');
    Route::post('/manage-admin-profile/update/{id}', [AdminController::class, 'manageAdminProfileUpdate'])->name('manage-admin-profile.update');

    Route::get('/add-users', [AdminController::class, 'addusers'])->name('add-users');
    Route::post('/add-user-excel', [AdminController::class, 'addExcelUsers'])->name('add-user-excel');
    Route::post('store-user', [AdminController::class, 'storeUser'])->name('store.user');

    // Free Plan Attempts Management
    Route::get('/free-plan-attempts', [\App\Http\Controllers\Admin\FreePlanAttemptController::class, 'index'])->name('free-plan-attempts.index');
    Route::post('/free-plan-attempts/unblock', [\App\Http\Controllers\Admin\FreePlanAttemptController::class, 'unblock'])->name('free-plan-attempts.unblock');
    Route::post('/free-plan-attempts/block', [\App\Http\Controllers\Admin\FreePlanAttemptController::class, 'block'])->name('free-plan-attempts.block');
    Route::delete('/free-plan-attempts', [\App\Http\Controllers\Admin\FreePlanAttemptController::class, 'destroy'])->name('free-plan-attempts.destroy');
    Route::get('/free-plan-attempts/{attempt}', [FreePlanAttemptController::class, 'show'])->name('free-plan-attempts.show');
    Route::post('/free-plan-attempts/block-identifier', [FreePlanAttemptController::class, 'blockIdentifier'])->name('free-plan-attempts.block-identifier');
    Route::post('/free-plan-attempts/unblock-identifier', [FreePlanAttemptController::class, 'unblockIdentifier'])->name('free-plan-attempts.unblock-identifier');
    Route::get('/free-plan-attempts-export', [FreePlanAttemptController::class, 'export'])->name('free-plan-attempts.export');

    // Sub Admin Management (Super Admin only)
    Route::middleware('role:Super Admin')->group(function () {
        Route::resource('subadmins', SubAdminController::class);
        Route::post('/subadmins/{subadmin}/toggle-status', [SubAdminController::class, 'toggleStatus'])->name('subadmins.toggle-status');
    });
});


// Root dashboard route that redirects based on role
Route::middleware(['auth', 'verified.custom'])->group(function () {
    Route::get('/dashboard', function () {
        if (auth()->user()->hasRole('Super Admin')) {
            return redirect()->route('admin.users');
        }
        return redirect()->route('user.dashboard');
    })->name('dashboard.redirect');


    Route::get('/profile', function () {
        if (auth()->user()->hasRole('Super Admin')) {
            return redirect()->route('admin.profile');
        }
        return redirect()->route('user.profile');
    })->name('profile.redirect');

});
