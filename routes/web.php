<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\ContributionModerationController;
use App\Http\Controllers\Admin\LeaderboardController;
use App\Http\Controllers\Admin\ProductManagementController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', AdminMiddleware::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/products', [ProductManagementController::class, 'index'])->name('products.index');
        Route::get('/products/create', [ProductManagementController::class, 'create'])->name('products.create');
        Route::post('/products', [ProductManagementController::class, 'store'])->name('products.store');
        Route::get('/products/{product}', [ProductManagementController::class, 'show'])->name('products.show');
        Route::get('/products/{product}/edit', [ProductManagementController::class, 'edit'])->name('products.edit');
        Route::post('/products/{product}', [ProductManagementController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductManagementController::class, 'destroy'])->name('products.destroy');

        Route::get('/contributions', [ContributionModerationController::class, 'index'])->name('contributions.index');
        Route::get('/contributions/{contribution}', [ContributionModerationController::class, 'show'])->name('contributions.show');
        Route::post('/contributions/{contribution}', [ContributionModerationController::class, 'update'])->name('contributions.update');
        Route::post('/contributions/{contribution}/approve', [ContributionModerationController::class, 'approve'])->name('contributions.approve');
        Route::post('/contributions/{contribution}/reject', [ContributionModerationController::class, 'reject'])->name('contributions.reject');
        Route::post('/contributions/{contribution}/flag', [ContributionModerationController::class, 'flag'])->name('contributions.flag');
        Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard.index');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])
            ->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');

        Route::post('/users', [UserController::class, 'store'])
            ->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');

        Route::post('/users/{user}/ban', [UserController::class, 'toggleBan'])->name('users.ban');
        Route::post('/users/{user}/verify', [UserController::class, 'verify'])->name('users.verify');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

        Route::get('/users/{user}/edit', [UserController::class, 'edit'])
            ->name('users.edit');

        Route::post('/users/{user}', [UserController::class, 'update'])
            ->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->name('users.destroy');
        Route::post('/users/{user}/toggle-role', [UserController::class, 'toggleRole'])->name('users.toggle-role');
    });

require __DIR__.'/auth.php';
