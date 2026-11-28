<?php

use App\Http\Controllers\VNPayController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatchController;


use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CertificateController;

use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\FarmingLogController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;


use App\Http\Controllers\Api\ReviewController;

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

// 1. Tính năng không cần đăng nhập (Public)
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');


Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slugOrId}', [ProductController::class, 'show']);
Route::get('/categories', [ProductController::class, 'categories']);

// Bảng lô đang bán - hiển thị ở trang chủ, không lộ thông tin nội bộ
Route::get('/available-batches', [ProductController::class, 'availableBatches']);
Route::get('/shipping-fee', [OrderController::class, 'shippingFee']);
Route::get('/subscription-plans', [SubscriptionController::class, 'plans']);


// Truy xuất nguồn gốc: khách quét QR là xem được ngay
Route::get('/traceability/{token}', [TraceabilityController::class, 'show']);
Route::get('/traceability/{token}/qr.svg', [TraceabilityController::class, 'qrCode']);

// Truy xuất nguồn gốc qua mã QR / mã lô
Route::get('/traceability/{code}', [TraceabilityController::class, 'show']);
Route::get('/traceability/{code}/qr.svg', [TraceabilityController::class, 'qrCode']);


// Thanh toán VNPay & Webhook VietQR
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
Route::post('/payments/vnpay-ipn', [VNPayController::class, 'ipn']);
Route::post('/vietqr/webhook', [PaymentController::class, 'sepayWebhook']);
Route::post('/sepay/webhook', [PaymentController::class, 'sepayWebhook']);

// 2. Tính năng đặt hàng hỗ trợ cả Guest và Khách đăng nhập
Route::middleware('jwt.optional')->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/demo-confirm', [OrderController::class, 'demoConfirm']);
    Route::post('/payments/confirm', [PaymentController::class, 'confirmPayment']);
});

// 3. Tính năng cho tài khoản đã đăng nhập
Route::middleware('jwt.auth')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);

    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/items', [CartController::class, 'store']);
    Route::patch('/cart/items/{id}', [CartController::class, 'update']);
    Route::delete('/cart/items/{id}', [CartController::class, 'destroy']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);

    Route::get('/my-subscriptions', [SubscriptionController::class, 'mySubscriptions']);
    Route::post('/subscriptions/{id}/pause', [SubscriptionController::class, 'pause']);


    // Khiếu nại của chính khách
    Route::get('/my-complaints', [ComplaintController::class, 'myComplaints']);
    Route::post('/complaints', [ComplaintController::class, 'store']);

    // Tải lên tệp ảnh/video minh chứng (Cloudinary hoặc local fallback)
    Route::post('/upload', [UploadController::class, 'upload']);
});



    Route::get('/my-complaints', [ComplaintController::class, 'myComplaints']);
    Route::post('/complaints', [ComplaintController::class, 'store']);
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::post('/upload', [UploadController::class, 'upload']);
});

Route::middleware(['jwt.auth', 'role:customer'])->group(function () {
    Route::post('/subscriptions', [SubscriptionController::class, 'subscribe']);
});

// 4. Tính năng của Sales / CSKH (và Admin)
Route::middleware(['jwt.auth', 'role:sales,admin'])->group(function () {
    // Duyệt đơn, điều phối giao hàng
    Route::get('/sales/orders', [SalesOrderController::class, 'index']);
    Route::patch('/orders/{id}/status', [SalesOrderController::class, 'updateStatus']);
    Route::post('/sales/orders/{id}/confirm', [SalesOrderController::class, 'confirm']);
    Route::post('/sales/orders/{id}/ship', [SalesOrderController::class, 'ship']);
    Route::post('/sales/orders/{id}/deliver', [SalesOrderController::class, 'deliver']);
    Route::post('/sales/orders/{id}/cancel', [SalesOrderController::class, 'cancel']);


    // Xử lý khiếu nại
    Route::get('/sales/complaints', [ComplaintController::class, 'index']);
    Route::post('/sales/complaints/{id}/handle', [ComplaintController::class, 'handle']);

    // Dời kỳ giao khi thiếu hàng

    Route::get('/sales/complaints', [ComplaintController::class, 'index']);
    Route::post('/sales/complaints/{id}/handle', [ComplaintController::class, 'handle']);

    Route::post('/sales/subscriptions/{id}/skip', [SubscriptionController::class, 'skip']);

    Route::get('/batch-alerts', [BatchController::class, 'alerts']);
    Route::patch('/batches/{id}/flash-sale', [BatchController::class, 'applyFlashSale']);
});


// Chỉ Nhân viên nông trại mới tạo lô và ghi nhật ký

// 5. Tính năng quản lý Lô hàng, Nhật ký, Chứng nhận & Hao hụt (Farm Staff & Admin)

Route::middleware(['jwt.auth', 'role:farm_staff,admin'])->group(function () {
    Route::get('/batches', [BatchController::class, 'index']);
    Route::get('/batches/{id}', [BatchController::class, 'show']);
    Route::post('/batches', [BatchController::class, 'store']);
    Route::put('/batches/{id}', [BatchController::class, 'update']);
    Route::delete('/batches/{id}', [BatchController::class, 'destroy']);

    Route::get('/batches-expiring-soon', [BatchController::class, 'expiringSoon']);
    Route::post('/batches/{id}/dispose', [BatchController::class, 'dispose']);
    Route::get('/batches/{id}/loss-records', [BatchController::class, 'lossRecords']);

    Route::get('/batches/{batchId}/farming-logs', [FarmingLogController::class, 'index']);
    Route::post('/batches/{batchId}/farming-logs', [FarmingLogController::class, 'store']);
    Route::delete('/batches/{batchId}/farming-logs/{logId}', [FarmingLogController::class, 'destroy']);


// Chỉ Nhân viên kho mới nhập kho và xuất hủy
Route::middleware(['jwt.auth', 'role:warehouse_staff,admin'])->group(function () {
    Route::post('/batches/{id}/harvest', [BatchController::class, 'harvest']);
    Route::post('/batches/{id}/dispose', [BatchController::class, 'dispose']);

    Route::get('/batches/{batchId}/certificates', [CertificateController::class, 'index']);
    Route::post('/batches/{batchId}/certificates', [CertificateController::class, 'store']);
    Route::delete('/batches/{batchId}/certificates/{id}', [CertificateController::class, 'destroy']);

});

// 6. Tính năng Quản trị viên (Admin)
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
