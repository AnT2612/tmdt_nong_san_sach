<?php

use App\Http\Controllers\VNPayController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\FarmingLogController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SalesOrderController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TraceabilityController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// 1. Tính năng không cần đăng nhập

// Xác thực. Giới hạn 5 lần/phút mỗi IP để chặn dò mật khẩu.
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Sản phẩm (khách vãng lai vẫn xem được)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slugOrId}', [ProductController::class, 'show']);
Route::get('/categories', [ProductController::class, 'categories']);

// Bảng lô đang bán - hiển thị ở trang chủ, không lộ thông tin nội bộ
Route::get('/available-batches', [ProductController::class, 'availableBatches']);

// Gói định kỳ đang bán
Route::get('/subscription-plans', [SubscriptionController::class, 'plans']);

// Truy xuất nguồn gốc: khách quét QR là xem được ngay
Route::get('/traceability/{token}', [TraceabilityController::class, 'show']);
Route::get('/traceability/{token}/qr.svg', [TraceabilityController::class, 'qrCode']);

// Cổng thanh toán VNPay Sandbox
Route::post('/vnpay/create', [VNPayController::class, 'createPayment']);
Route::get('/vnpay/return', [VNPayController::class, 'returnPayment']);
Route::get('/vnpay/ipn', [VNPayController::class, 'ipn']);

// Webhook tự động đối soát ngân hàng VietQR (SePay / Casso / Ngân hàng)
Route::post('/vietqr/webhook', [PaymentController::class, 'sepayWebhook']);
Route::post('/sepay/webhook', [PaymentController::class, 'sepayWebhook']);

// Alias tương thích ngược cho payments/vnpay
Route::post('/payments/vnpay/create', [VNPayController::class, 'createPayment']);
Route::get('/payments/vnpay/return', [VNPayController::class, 'returnPayment']);
Route::get('/payments/vnpay/ipn', [VNPayController::class, 'ipn']);

// 2. Tính năng cần đăng nhập (mọi vai trò)
Route::middleware('jwt.auth')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Đơn hàng của chính khách
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);

    // Gói định kỳ của chính khách
    Route::get('/my-subscriptions', [SubscriptionController::class, 'mySubscriptions']);
    Route::post('/subscriptions/{id}/pause', [SubscriptionController::class, 'pause']);

    // Khiếu nại của chính khách
    Route::get('/my-complaints', [ComplaintController::class, 'myComplaints']);
    Route::post('/complaints', [ComplaintController::class, 'store']);

    // Tải lên tệp ảnh/video minh chứng (Cloudinary hoặc local fallback)
    Route::post('/upload', [UploadController::class, 'upload']);
});

// 3A. Đặt hàng (cho phép cả khách vãng lai chưa đăng nhập)
Route::middleware('jwt.optional')->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/demo-confirm', [OrderController::class, 'demoConfirm']);
    Route::post('/payments/confirm', [PaymentController::class, 'confirmPayment']);
});

// 3B. Khách hàng đăng ký gói định kỳ
Route::middleware(['jwt.auth', 'role:customer'])->group(function () {
    Route::post('/subscriptions', [SubscriptionController::class, 'subscribe']);
});

// 4. Nhân viên bán hàng / CSKH (Sales)
Route::middleware(['jwt.auth', 'role:sales,admin'])->group(function () {
    // Duyệt đơn, điều phối giao hàng
    Route::get('/sales/orders', [SalesOrderController::class, 'index']);
    Route::post('/sales/orders/{id}/confirm', [SalesOrderController::class, 'confirm']);
    Route::post('/sales/orders/{id}/ship', [SalesOrderController::class, 'ship']);
    Route::post('/sales/orders/{id}/deliver', [SalesOrderController::class, 'deliver']);
    Route::post('/sales/orders/{id}/cancel', [SalesOrderController::class, 'cancel']);

    // Xử lý khiếu nại
    Route::get('/sales/complaints', [ComplaintController::class, 'index']);
    Route::post('/sales/complaints/{id}/handle', [ComplaintController::class, 'handle']);

    // Dời kỳ giao khi thiếu hàng
    Route::post('/sales/subscriptions/{id}/skip', [SubscriptionController::class, 'skip']);
});

// 5. Nhân viên nông trại và nhân viên kho
Route::middleware(['jwt.auth', 'role:farm_staff,warehouse_staff,admin'])->group(function () {
    Route::get('/batches', [BatchController::class, 'index']);
    Route::get('/batches/{id}/transactions', [BatchController::class, 'transactions']);
    Route::get('/batches-expiring-soon', [BatchController::class, 'expiringSoon']);
});

// Chỉ Nhân viên nông trại mới tạo lô và ghi nhật ký
Route::middleware(['jwt.auth', 'role:farm_staff,admin'])->group(function () {
    Route::post('/batches', [BatchController::class, 'store']);

    Route::get('/batches/{batchId}/farming-logs', [FarmingLogController::class, 'index']);
    Route::post('/batches/{batchId}/farming-logs', [FarmingLogController::class, 'store']);
    Route::delete('/batches/{batchId}/farming-logs/{logId}', [FarmingLogController::class, 'destroy']);
});

// Chỉ Nhân viên kho mới nhập kho và xuất hủy
Route::middleware(['jwt.auth', 'role:warehouse_staff,admin'])->group(function () {
    Route::post('/batches/{id}/harvest', [BatchController::class, 'harvest']);
    Route::post('/batches/{id}/dispose', [BatchController::class, 'dispose']);
});

// 6. Quản trị viên (Admin)
Route::middleware(['jwt.auth', 'role:admin'])->group(function () {
    // Người dùng và phân quyền
    Route::get('/admin/users', [UserController::class, 'index']);
    Route::put('/admin/users/{id}/role', [UserController::class, 'updateRole']);
    Route::post('/admin/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);

    // Báo cáo thống kê
    Route::get('/admin/reports/overview', [ReportController::class, 'overview']);
    Route::get('/admin/reports/revenue', [ReportController::class, 'revenue']);
    Route::get('/admin/reports/inventory', [ReportController::class, 'inventory']);
    Route::get('/admin/reports/top-products', [ReportController::class, 'topProducts']);
});
