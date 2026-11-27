<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\FarmingLogController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TraceabilityController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// 1. Tính năng không cần đăng nhập
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Sản phẩm
// Sản phẩm & danh mục
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slugOrId}', [ProductController::class, 'show']);
Route::get('/categories', [ProductController::class, 'categories']);
Route::get('/available-batches', [ProductController::class, 'availableBatches']);

// Gói định kỳ đang bán
Route::get('/subscription-plans', [SubscriptionController::class, 'plans']);

// Truy xuất nguồn gốc qua mã QR
Route::get('/traceability/{token}', [TraceabilityController::class, 'show']);
Route::get('/traceability/{token}/qr.svg', [TraceabilityController::class, 'qrCode']);

// 2. Tính năng cần đăng nhập (mọi vai trò)
Route::middleware('jwt.auth')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Gói định kỳ của chính khách
    Route::get('/my-subscriptions', [SubscriptionController::class, 'mySubscriptions']);
    Route::post('/subscriptions/{id}/pause', [SubscriptionController::class, 'pause']);
});

// 3B. Khách hàng đăng ký gói định kỳ
Route::middleware(['jwt.auth', 'role:customer'])->group(function () {
    Route::post('/subscriptions', [SubscriptionController::class, 'subscribe']);
});

// 4. Nhân viên bán hàng / CSKH (Sales)
Route::middleware(['jwt.auth', 'role:sales,admin'])->group(function () {
    Route::post('/sales/subscriptions/{id}/skip', [SubscriptionController::class, 'skip']);
});

// 5. Nhân viên nông trại và nhân viên kho
Route::middleware(['jwt.auth', 'role:farm_staff,warehouse_staff,admin'])->group(function () {
    Route::get('/batches', [BatchController::class, 'index']);
});

Route::middleware(['jwt.auth', 'role:farm_staff,admin'])->group(function () {
    Route::post('/batches', [BatchController::class, 'store']);

    Route::get('/batches/{batchId}/farming-logs', [FarmingLogController::class, 'index']);
    Route::post('/batches/{batchId}/farming-logs', [FarmingLogController::class, 'store']);
    Route::delete('/batches/{batchId}/farming-logs/{logId}', [FarmingLogController::class, 'destroy']);
});

// 6. Quản trị viên (Admin)
Route::middleware(['jwt.auth', 'role:admin'])->group(function () {
    Route::get('/admin/users', [UserController::class, 'index']);
    Route::put('/admin/users/{id}/role', [UserController::class, 'updateRole']);
    Route::post('/admin/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
});
