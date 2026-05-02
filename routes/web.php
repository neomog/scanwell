<?php

use App\Http\Controllers\Admin\BillingController as AdminBillingController;
use App\Http\Controllers\Admin\ContributionModerationController;
use App\Http\Controllers\Admin\LeaderboardController;
use App\Http\Controllers\Admin\ProductManagementController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
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
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/prices/{price}/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::post('/billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
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

        Route::get('/plans', [SubscriptionPlanController::class, 'index'])->name('plans.index');
        Route::get('/plans/create', [SubscriptionPlanController::class, 'create'])->name('plans.create');
        Route::post('/plans', [SubscriptionPlanController::class, 'store'])->name('plans.store');
        Route::get('/plans/{plan}/edit', [SubscriptionPlanController::class, 'edit'])->name('plans.edit');
        Route::post('/plans/{plan}', [SubscriptionPlanController::class, 'update'])->name('plans.update');
        Route::post('/plans/{plan}/toggle-active', [SubscriptionPlanController::class, 'toggleActive'])->name('plans.toggle-active');
        Route::delete('/plans/{plan}', [SubscriptionPlanController::class, 'destroy'])->name('plans.destroy');
        Route::get('/plans/{plan}/prices/create', [SubscriptionPlanController::class, 'createPrice'])->name('plans.prices.create');
        Route::post('/plans/{plan}/prices', [SubscriptionPlanController::class, 'storePrice'])->name('plans.prices.store');
        Route::get('/prices/{price}/edit', [SubscriptionPlanController::class, 'editPrice'])->name('prices.edit');
        Route::post('/prices/{price}', [SubscriptionPlanController::class, 'updatePrice'])->name('prices.update');
        Route::post('/prices/{price}/toggle-active', [SubscriptionPlanController::class, 'togglePriceActive'])->name('prices.toggle-active');
        Route::delete('/prices/{price}', [SubscriptionPlanController::class, 'destroyPrice'])->name('prices.destroy');

        Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show'])->name('subscriptions.show');
        Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
        Route::post('/subscriptions/{subscription}/refund', [SubscriptionController::class, 'refund'])->name('subscriptions.refund');

        Route::get('/billing', [AdminBillingController::class, 'index'])->name('billing.index');
        Route::get('/billing/invoices', [AdminBillingController::class, 'invoices'])->name('billing.invoices');
        Route::get('/billing/transactions', [AdminBillingController::class, 'transactions'])->name('billing.transactions');
        Route::get('/billing/refunds', [AdminBillingController::class, 'refunds'])->name('billing.refunds');
        Route::get('/billing/failures', [AdminBillingController::class, 'failures'])->name('billing.failures');

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
        Route::post('/users/{user}/subscription/change', [UserController::class, 'changeSubscription'])->name('users.subscription.change');
    });

require __DIR__.'/auth.php';
