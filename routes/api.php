<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductContributionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\UserPreferenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Protected routes
Route::middleware('auth:sanctum', 'verified')->group(function () {
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('/email/resend', [AuthController::class, 'resendVerification']);

    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);


});

// oauth routes
Route::prefix('auth')->group(function () {
    Route::post('/auth/google', [SocialAuthController::class, 'googleAuth']);
//    Route::get('/{provider}/redirect', [SocialAuthController::class, 'redirectToProvider']);
//    Route::get('/{provider}/callback', [SocialAuthController::class, 'handleProviderCallback']);
});

//Route::prefix('v1')->group(function () {
//    // Product search (public)
//    Route::get('/products/search', [ProductController::class, 'search']);
//    Route::get('/products/{barcode}', [ProductController::class, 'show']);
//});

Route::prefix('v1')->group(function () {
    // Product search and view (public)
    Route::get('/products/search', [ProductController::class, 'search']);
    Route::get('/products/barcode/{barcode}', [ProductController::class, 'findByBarcode']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
});


// Protected routes (require authentication)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    // Scan routes
    Route::post('/scan', [ScanController::class, 'scan']);
    Route::get('/scans', [ScanController::class, 'history']);
    Route::get('/scans/{id}', [ScanController::class, 'show']);
    Route::delete('/scans/{id}', [ScanController::class, 'destroy']);

    // User preferences
    Route::get('/preferences', [UserPreferenceController::class, 'show']);
    Route::put('/preferences', [UserPreferenceController::class, 'update']);
    Route::get('/recommendations', [UserPreferenceController::class, 'recommendations']);

    // Product contributions
    Route::post('/products/{barcode}/contribute', [ProductContributionController::class, 'store']);
    Route::get('/my-contributions', [ProductContributionController::class, 'userContributions']);

    // Alternative products
    Route::get('/products/{id}/alternatives', [ProductController::class, 'alternatives']);

    // User's scanned products
    Route::get('/my/products', [ProductController::class, 'userProducts']);
    Route::post('/products/{id}/favorite', [ProductController::class, 'toggleFavorite']);
});

// Admin routes
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/contributions/pending', [ProductContributionController::class, 'pending']);
    Route::post('/contributions/{id}/approve', [ProductContributionController::class, 'approve']);
    Route::post('/contributions/{id}/reject', [ProductContributionController::class, 'reject']);

    // Manual product management
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
});
