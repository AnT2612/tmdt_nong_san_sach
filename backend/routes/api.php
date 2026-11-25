<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// 1. Tính năng không cần đăng nhập
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Sản phẩm & danh mục
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slugOrId}', [ProductController::class, 'show']);
Route::get('/categories', [ProductController::class, 'categories']);
Route::get('/available-batches', [ProductController::class, 'availableBatches']);

// 2. Tính năng cần đăng nhập (mọi vai trò)
Route::middleware('jwt.auth')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

// 5. Nhân viên nông trại và nhân viên kho
Route::middleware(['jwt.auth', 'role:farm_staff,warehouse_staff,admin'])->group(function () {
    Route::get('/batches', [BatchController::class, 'index']);
});

Route::middleware(['jwt.auth', 'role:farm_staff,admin'])->group(function () {
    Route::post('/batches', [BatchController::class, 'store']);
});

// 6. Quản trị viên (Admin)
Route::middleware(['jwt.auth', 'role:admin'])->group(function () {
    Route::get('/admin/users', [UserController::class, 'index']);
    Route::put('/admin/users/{id}/role', [UserController::class, 'updateRole']);
    Route::post('/admin/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
});
