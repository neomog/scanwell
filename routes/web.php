<?php

use App\Http\Controllers\Admin\BillingController as AdminBillingController;
use App\Http\Controllers\Admin\ContributionModerationController;
use App\Http\Controllers\Admin\LeaderboardController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\SupportCaseController as AdminSupportCaseController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ProductManagementController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ScanProviderController;
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
        Route::get('/products', [ProductManagementController::class, 'index'])->middleware('can:products.view')->name('products.index');
        Route::get('/products/create', [ProductManagementController::class, 'create'])->middleware('can:products.edit')->name('products.create');
        Route::post('/products', [ProductManagementController::class, 'store'])->middleware('can:products.edit')->name('products.store');
        Route::get('/products/{product}', [ProductManagementController::class, 'show'])->middleware('can:products.view')->name('products.show');
        Route::get('/products/{product}/edit', [ProductManagementController::class, 'edit'])->middleware('can:products.edit')->name('products.edit');
        Route::post('/products/{product}', [ProductManagementController::class, 'update'])->middleware('can:products.edit')->name('products.update');
        Route::delete('/products/{product}', [ProductManagementController::class, 'destroy'])->middleware('can:products.edit')->name('products.destroy');

        Route::get('/contributions', [ContributionModerationController::class, 'index'])->middleware('can:submissions.view')->name('contributions.index');
        Route::get('/contributions/{contribution}', [ContributionModerationController::class, 'show'])->middleware('can:submissions.view')->name('contributions.show');
        Route::post('/contributions/{contribution}', [ContributionModerationController::class, 'update'])->middleware('can:submissions.approve')->name('contributions.update');
        Route::post('/contributions/{contribution}/approve', [ContributionModerationController::class, 'approve'])->middleware('can:submissions.approve')->name('contributions.approve');
        Route::post('/contributions/{contribution}/reject', [ContributionModerationController::class, 'reject'])->middleware('can:submissions.approve')->name('contributions.reject');
        Route::post('/contributions/{contribution}/flag', [ContributionModerationController::class, 'flag'])->middleware('can:submissions.approve')->name('contributions.flag');
        Route::get('/leaderboard', [LeaderboardController::class, 'index'])->middleware('can:leaderboard.view')->name('leaderboard.index');
        Route::get('/notifications', [AdminNotificationController::class, 'index'])->middleware('can:notifications.view')->name('notifications.index');
        Route::get('/notifications/create', [AdminNotificationController::class, 'create'])->middleware('can:notifications.manage')->name('notifications.create');
        Route::post('/notifications', [AdminNotificationController::class, 'store'])->middleware('can:notifications.manage')->name('notifications.store');
        Route::get('/notifications/{notification}', [AdminNotificationController::class, 'show'])->middleware('can:notifications.view')->name('notifications.show');
        Route::post('/notifications/{notification}/send', [AdminNotificationController::class, 'send'])->middleware('can:notifications.manage')->name('notifications.send');
        Route::get('/scanning/providers', [ScanProviderController::class, 'index'])->middleware('can:scanning.manage')->name('scanning.providers.index');
        Route::post('/scanning/providers/{scanProvider}', [ScanProviderController::class, 'update'])->middleware('can:scanning.manage')->name('scanning.providers.update');
        Route::post('/scanning/providers/{scanProvider}/toggle-active', [ScanProviderController::class, 'toggleActive'])->middleware('can:scanning.manage')->name('scanning.providers.toggle-active');
        Route::post('/scanning/providers/{scanProvider}/sync-products', [ScanProviderController::class, 'syncProducts'])->middleware('can:scanning.manage')->name('scanning.providers.sync-products');
        Route::get('/support', [AdminSupportCaseController::class, 'index'])->middleware('can:support.view')->name('support.index');
        Route::get('/support/create', [AdminSupportCaseController::class, 'create'])->middleware('can:support.manage')->name('support.create');
        Route::post('/support', [AdminSupportCaseController::class, 'store'])->middleware('can:support.manage')->name('support.store');
        Route::get('/support/{supportCase}', [AdminSupportCaseController::class, 'show'])->middleware('can:support.view')->name('support.show');
        Route::post('/support/{supportCase}', [AdminSupportCaseController::class, 'update'])->middleware('can:support.manage')->name('support.update');
        Route::post('/support/{supportCase}/messages', [AdminSupportCaseController::class, 'message'])->middleware('can:support.manage')->name('support.message');

        Route::get('/plans', [SubscriptionPlanController::class, 'index'])->middleware('can:plans.manage')->name('plans.index');
        Route::get('/plans/create', [SubscriptionPlanController::class, 'create'])->middleware('can:plans.manage')->name('plans.create');
        Route::post('/plans', [SubscriptionPlanController::class, 'store'])->middleware('can:plans.manage')->name('plans.store');
        Route::get('/plans/{plan}/edit', [SubscriptionPlanController::class, 'edit'])->middleware('can:plans.manage')->name('plans.edit');
        Route::post('/plans/{plan}', [SubscriptionPlanController::class, 'update'])->middleware('can:plans.manage')->name('plans.update');
        Route::post('/plans/{plan}/toggle-active', [SubscriptionPlanController::class, 'toggleActive'])->middleware('can:plans.manage')->name('plans.toggle-active');
        Route::delete('/plans/{plan}', [SubscriptionPlanController::class, 'destroy'])->middleware('can:plans.manage')->name('plans.destroy');
        Route::get('/plans/{plan}/prices/create', [SubscriptionPlanController::class, 'createPrice'])->middleware('can:plans.manage')->name('plans.prices.create');
        Route::post('/plans/{plan}/prices', [SubscriptionPlanController::class, 'storePrice'])->middleware('can:plans.manage')->name('plans.prices.store');
        Route::get('/prices/{price}/edit', [SubscriptionPlanController::class, 'editPrice'])->middleware('can:plans.manage')->name('prices.edit');
        Route::post('/prices/{price}', [SubscriptionPlanController::class, 'updatePrice'])->middleware('can:plans.manage')->name('prices.update');
        Route::post('/prices/{price}/toggle-active', [SubscriptionPlanController::class, 'togglePriceActive'])->middleware('can:plans.manage')->name('prices.toggle-active');
        Route::delete('/prices/{price}', [SubscriptionPlanController::class, 'destroyPrice'])->middleware('can:plans.manage')->name('prices.destroy');

        Route::get('/subscriptions', [SubscriptionController::class, 'index'])->middleware('can:subscriptions.manage')->name('subscriptions.index');
        Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show'])->middleware('can:subscriptions.manage')->name('subscriptions.show');
        Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->middleware('can:subscriptions.manage')->name('subscriptions.cancel');
        Route::post('/subscriptions/{subscription}/refund', [SubscriptionController::class, 'refund'])->middleware('can:subscriptions.manage')->name('subscriptions.refund');

        Route::get('/billing', [AdminBillingController::class, 'index'])->middleware('can:billing.view')->name('billing.index');
        Route::get('/billing/invoices', [AdminBillingController::class, 'invoices'])->middleware('can:billing.view')->name('billing.invoices');
        Route::get('/billing/transactions', [AdminBillingController::class, 'transactions'])->middleware('can:billing.view')->name('billing.transactions');
        Route::get('/billing/refunds', [AdminBillingController::class, 'refunds'])->middleware('can:billing.view')->name('billing.refunds');
        Route::get('/billing/failures', [AdminBillingController::class, 'failures'])->middleware('can:billing.view')->name('billing.failures');

        Route::get('/users', [UserController::class, 'index'])->middleware('can:users.view')->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])
            ->middleware('can:users.manage')
            ->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->middleware('can:users.manage')->name('users.store');

        Route::get('/users/{user}', [UserController::class, 'show'])->middleware('can:users.view')->name('users.show');

        Route::post('/users/{user}/ban', [UserController::class, 'toggleBan'])->middleware('can:users.manage')->name('users.ban');
        Route::post('/users/{user}/verify', [UserController::class, 'verify'])->middleware('can:users.manage')->name('users.verify');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('can:users.manage')->name('users.reset-password');

        Route::get('/users/{user}/edit', [UserController::class, 'edit'])
            ->middleware('can:users.manage')
            ->name('users.edit');

        Route::post('/users/{user}', [UserController::class, 'update'])
            ->middleware('can:users.manage')
            ->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->middleware('can:users.manage')
            ->name('users.destroy');
        Route::post('/users/{user}/toggle-role', [UserController::class, 'toggleRole'])->middleware('can:users.manage')->name('users.toggle-role');
        Route::post('/users/{user}/subscription/change', [UserController::class, 'changeSubscription'])->middleware('can:subscriptions.manage')->name('users.subscription.change');

        Route::get('/roles', [RoleController::class, 'index'])->middleware('can:roles.manage')->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->middleware('can:roles.manage')->name('roles.store');
        Route::post('/roles/{role}', [RoleController::class, 'update'])->middleware('can:roles.manage')->name('roles.update');

        Route::get('/permissions', [PermissionController::class, 'index'])->middleware('can:permissions.manage')->name('permissions.index');
        Route::post('/permissions', [PermissionController::class, 'store'])->middleware('can:permissions.manage')->name('permissions.store');
        Route::post('/permissions/{permission}', [PermissionController::class, 'update'])->middleware('can:permissions.manage')->name('permissions.update');
    });

require __DIR__.'/auth.php';
