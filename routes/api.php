<?php

use App\Http\Controllers\Api\BillingController as ApiBillingController;
use App\Http\Controllers\Api\Admin\NotificationController as ApiAdminNotificationController;
use App\Http\Controllers\Api\NotificationController as ApiNotificationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductContributionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\UserPreferenceController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/stripe/webhook', StripeWebhookController::class)->name('api.stripe.webhook');

// Protected routes
Route::middleware('auth:sanctum', 'verified')->group(function () {
    //    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    //        ->middleware('signed')
    //        ->name('verification.verify');
    Route::post('/email/resend', [AuthController::class, 'resendVerification']);

    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

});

// oauth routes
Route::prefix('auth')->group(function () {
    //    Route::post('/google', [SocialAuthController::class, 'googleAuth']);
    Route::post('/google', [SocialAuthController::class, 'googleMobileAuth']);
    //    Route::get('/{provider}/redirect', [SocialAuthController::class, 'redirectToProvider']);
    //    Route::get('/{provider}/callback', [SocialAuthController::class, 'handleProviderCallback']);
});

// Route::prefix('v1')->group(function () {
//    // Product search (public)
//    Route::get('/products/search', [ProductController::class, 'search']);
//    Route::get('/products/{barcode}', [ProductController::class, 'show']);
// });

Route::prefix('v1')->group(function () {
    // Product search and view (public)
    Route::get('/products/search', [ProductController::class, 'search']);
    Route::get('/products/barcode/{barcode}', [ProductController::class, 'findByBarcode']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::get('/billing/plans', [ApiBillingController::class, 'plans']);
});

// Protected routes (require authentication)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/billing/subscription', [ApiBillingController::class, 'subscription']);
    Route::get('/billing/history', [ApiBillingController::class, 'history']);
    Route::get('/billing/invoices', [ApiBillingController::class, 'invoices']);
    Route::get('/billing/transactions', [ApiBillingController::class, 'transactions']);
    Route::get('/billing/failed-payments', [ApiBillingController::class, 'failedPayments']);
    Route::post('/billing/checkout', [ApiBillingController::class, 'checkout']);
    Route::post('/billing/cancel', [ApiBillingController::class, 'cancel']);

    // Scan routes
    Route::post('/scan', [ScanController::class, 'scan']);
    Route::get('/scans', [ScanController::class, 'history']);
    Route::get('/scans/{id}', [ScanController::class, 'show']);
    Route::delete('/scans/{id}', [ScanController::class, 'destroy']);

    // User preferences
    Route::get('/preferences', [UserPreferenceController::class, 'show']);
    Route::put('/preferences', [UserPreferenceController::class, 'update']);
    Route::get('/recommendations', [UserPreferenceController::class, 'recommendations'])
        ->middleware('plan.feature:recommendations.enabled');

    Route::get('/notifications', [ApiNotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [ApiNotificationController::class, 'unreadCount']);
    Route::get('/notifications/{notification}', [ApiNotificationController::class, 'show']);
    Route::post('/notifications/{notification}/read', [ApiNotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [ApiNotificationController::class, 'markAllRead']);
    Route::post('/devices/push-tokens', [ApiNotificationController::class, 'registerPushToken']);
    Route::delete('/devices/push-tokens', [ApiNotificationController::class, 'unregisterPushToken']);

    // Product contributions
    Route::post('/products/{barcode}/contribute', [ProductContributionController::class, 'store'])
        ->middleware('plan.feature:contributions.enabled');
    Route::put('/my-contributions/{id}', [ProductContributionController::class, 'update']);
    Route::get('/my-contributions', [ProductContributionController::class, 'userContributions']);
    Route::get('/contributions/leaderboard', [ProductContributionController::class, 'leaderboard']);

    // Alternative products
    Route::get('/products/{id}/alternatives', [ProductController::class, 'alternatives']);

    // User's scanned products
    Route::get('/my/products', [ProductController::class, 'userProducts']);
    Route::post('/products/{id}/favorite', [ProductController::class, 'toggleFavorite']);
});

// Admin routes
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/contributions/pending', [ProductContributionController::class, 'pending'])->middleware('can:submissions.view');
    Route::put('/contributions/{id}', [ProductContributionController::class, 'moderateUpdate'])->middleware('can:submissions.approve');
    Route::post('/contributions/{id}/approve', [ProductContributionController::class, 'approve'])->middleware('can:submissions.approve');
    Route::post('/contributions/{id}/reject', [ProductContributionController::class, 'reject'])->middleware('can:submissions.approve');
    Route::post('/contributions/{id}/flag', [ProductContributionController::class, 'flag'])->middleware('can:submissions.approve');

    // Manual product management
    Route::post('/products', [ProductController::class, 'store'])->middleware('can:products.edit');
    Route::put('/products/{id}', [ProductController::class, 'update'])->middleware('can:products.edit');
    Route::delete('/products/{id}', [ProductController::class, 'destroy'])->middleware('can:products.edit');

    Route::get('/notifications', [ApiAdminNotificationController::class, 'index'])->middleware('can:notifications.view');
    Route::post('/notifications', [ApiAdminNotificationController::class, 'store'])->middleware('can:notifications.manage');
    Route::get('/notifications/{notification}', [ApiAdminNotificationController::class, 'show'])->middleware('can:notifications.view');
    Route::post('/notifications/{notification}/send', [ApiAdminNotificationController::class, 'send'])->middleware('can:notifications.manage');
});
