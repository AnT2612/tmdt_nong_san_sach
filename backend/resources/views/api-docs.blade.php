<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tài liệu API v1 - Website Nông Sản Sạch</title>
    <link rel="stylesheet" href="{{ asset('css/api-docs.css') }}">
</head>
<body>
<div class="container">

    <h1>Tài liệu RESTful API (/api/v1) - Website Nông Sản Sạch</h1>
    <p><strong>Nhóm 8</strong> - Lớp CĐ CNTT 24WebC - Trường Cao đẳng Kỹ thuật Cao Thắng</p>

    <div class="info-box">
        <p><strong>Base URL:</strong> <code>{{ url('/api/v1') }}</code></p>
        <p><strong>Cơ sở dữ liệu:</strong> 19 bảng chuẩn 3NF (<code>roles</code>, <code>users</code>, <code>categories</code>, <code>products</code>, <code>batches</code>, <code>farming_logs</code>, <code>loss_records</code>, <code>batch_alerts</code>, <code>certificates</code>, <code>subscriptions</code>, <code>subscription_items</code>, <code>delivery_schedules</code>, <code>orders</code>, <code>order_details</code>, <code>payments</code>, <code>complaints</code>, <code>addresses</code>, <code>point_transactions</code>, <code>reviews</code>)</p>
        <p><strong>Xác thực:</strong> JWT Bearer Token trong Header: <code>Authorization: Bearer &lt;token&gt;</code></p>
    </div>

    <div class="menu">
        <strong>Mục lục nhanh:</strong>
        <ul>
            <li><a href="#accounts">1. Tài khoản mẫu &amp; Phân quyền</a></li>
            <li><a href="#errors">2. Cấu trúc phản hồi &amp; Bộ bắt lỗi tập trung</a></li>
            <li><a href="#auth">3. Xác thực &amp; Địa chỉ (/auth/*, /addresses)</a></li>
            <li><a href="#products">4. Sản phẩm &amp; Danh mục (/products, /categories)</a></li>
            <li><a href="#batches">5. Quản lý Lô hàng CRUD &amp; Hao hụt (/batches*)</a></li>
            <li><a href="#traceability">6. Nhật ký canh tác, Chứng nhận &amp; Mã QR</a></li>
            <li><a href="#orders">7. Đặt hàng, Tính ship &amp; Duyệt đơn FEFO (/orders*)</a></li>
            <li><a href="#subscriptions">8. Gói rau định kỳ &amp; Lịch giao</a></li>
            <li><a href="#payments">9. Thanh toán VNPay &amp; VietQR</a></li>
            <li><a href="#complaints">10. Khiếu nại, Đánh giá, Upload &amp; Báo cáo</a></li>
        </ul>
    </div>

    <h2 id="accounts">1. Tài khoản mẫu &amp; Phân quyền</h2>
    <table>
        <thead>
            <tr>
                <th>Vai trò (bảng <code>roles</code>)</th>
                <th>Email</th>
                <th>Mật khẩu</th>
                <th>Chức năng chính</th>
            </tr>
        </thead>
        <tbody>
            <tr><td><code>admin</code></td><td><code>admin@nongsan.vn</code></td><td><code>12345678</code></td><td>Toàn quyền: báo cáo, quản lý tài khoản &amp; phân quyền</td></tr>
            <tr><td><code>sales</code></td><td><code>sales@nongsan.vn</code></td><td><code>12345678</code></td><td>Duyệt đơn FEFO, áp giá xả hàng, xử lý khiếu nại</td></tr>
            <tr><td><code>farm_staff</code></td><td><code>nongtrai@nongsan.vn</code> / <code>kho@nongsan.vn</code></td><td><code>12345678</code></td><td>CRUD lô hàng, nhật ký canh tác, chứng nhận, xuất hủy</td></tr>
            <tr><td><code>customer</code></td><td><code>khachhang@nongsan.vn</code></td><td><code>12345678</code></td><td>Mua hàng, gói định kỳ, tích điểm, khiếu nại, đánh giá</td></tr>
        </tbody>
    </table>

    <h2 id="errors">2. Cấu trúc phản hồi &amp; Bộ bắt lỗi tập trung</h2>
    <pre>{ "status": "error", "success": false, "message": "Sản phẩm Cà chua bi chỉ còn 17 kg, không đủ 50 kg." }</pre>
    <table>
        <thead><tr><th>Mã HTTP</th><th>Ý nghĩa</th></tr></thead>
        <tbody>
            <tr><td><code>200 / 201</code></td><td>Thành công / Tạo mới thành công</td></tr>
            <tr><td><code>401</code></td><td>Chưa đăng nhập hoặc JWT token hết hạn</td></tr>
            <tr><td><code>403</code></td><td>Sai vai trò phân quyền (Middleware <code>role:...</code>)</td></tr>
            <tr><td><code>404</code></td><td>Không tìm thấy tài nguyên yêu cầu</td></tr>
            <tr><td><code>422</code></td><td>Lỗi validation hoặc <code>InsufficientStockException</code> khi trừ kho FEFO</td></tr>
        </tbody>
    </table>

    <h2 id="auth">3. Xác thực &amp; Địa chỉ</h2>
    <table>
        <thead><tr><th>Method</th><th>Endpoint</th><th>Mô tả</th><th>Quyền</th></tr></thead>
        <tbody>
            <tr><td><span class="method method-dark">POST</span></td><td><code>/api/v1/auth/register</code></td><td>Đăng ký tài khoản khách hàng (<code>ho_ten</code>, <code>email</code>, <code>mat_khau</code>)</td><td>Public</td></tr>
            <tr><td><span class="method method-dark">POST</span></td><td><code>/api/v1/auth/login</code></td><td>Đăng nhập lấy JWT token</td><td>Public</td></tr>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/auth/me</code></td><td>Thông tin tài khoản hiện tại</td><td>JWT</td></tr>
            <tr><td><span class="method">PUT</span></td><td><code>/api/v1/auth/profile</code></td><td>Cập nhật hồ sơ cá nhân</td><td>JWT</td></tr>
            <tr><td><span class="method method-dark">POST</span></td><td><code>/api/v1/auth/logout</code></td><td>Đăng xuất</td><td>JWT</td></tr>
            <tr><td><span class="method">GET / POST / DELETE</span></td><td><code>/api/v1/addresses</code></td><td>Quản lý sổ địa chỉ (<code>vi_do</code>, <code>kinh_do</code>)</td><td>JWT</td></tr>
            <tr><td><span class="method">GET / POST / PATCH / DELETE</span></td><td><code>/api/v1/cart</code>, <code>/api/v1/cart/items/{id}</code></td><td>Quản lý giỏ hàng server-side của khách</td><td>JWT</td></tr>
        </tbody>
    </table>

    <h2 id="products">4. Sản phẩm &amp; Danh mục</h2>
    <table>
        <thead><tr><th>Method</th><th>Endpoint</th><th>Mô tả</th><th>Quyền</th></tr></thead>
        <tbody>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/categories</code></td><td>Danh sách danh mục nông sản</td><td>Public</td></tr>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/products</code></td><td>Danh sách sản phẩm kèm tổng tồn khả dụng FEFO</td><td>Public</td></tr>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/products/{slugOrId}</code></td><td>Chi tiết sản phẩm &amp; đánh giá (<code>reviews</code>)</td><td>Public</td></tr>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/available-batches</code></td><td>Danh sách lô đang bán xếp theo <code>ngay_het_han ASC</code></td><td>Public</td></tr>
        </tbody>
    </table>

    <h2 id="batches">5. Quản lý Lô hàng CRUD &amp; Hao hụt (<code>/batches*</code>)</h2>
    <table>
        <thead><tr><th>Method</th><th>Endpoint</th><th>Mô tả</th><th>Quyền</th></tr></thead>
        <tbody>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/batches</code></td><td>Danh sách lô hàng xếp theo FEFO</td><td>Farm Staff / Admin</td></tr>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/batches/{id}</code></td><td>Chi tiết 1 lô hàng kèm nhật ký, chứng nhận, hao hụt</td><td>Farm Staff / Admin</td></tr>
            <tr><td><span class="method method-dark">POST</span></td><td><code>/api/v1/batches</code></td><td>Tạo lô mới &amp; sinh mã QR</td><td>Farm Staff / Admin</td></tr>
            <tr><td><span class="method">PUT</span></td><td><code>/api/v1/batches/{id}</code></td><td>Cập nhật thông tin lô hàng (thu hoạch, hạn dùng, số lượng nhập)</td><td>Farm Staff / Admin</td></tr>
            <tr><td><span class="method">PATCH</span></td><td><code>/api/v1/batches/{id}/flash-sale</code></td><td>Áp giá xả hàng cận hạn (<code>gia_xa_hang</code>) &amp; đóng cảnh báo</td><td>Sales / Admin</td></tr>
            <tr><td><span class="method">DELETE</span></td><td><code>/api/v1/batches/{id}</code></td><td>Xoá lô chưa phát sinh đơn hàng hoặc hao hụt</td><td>Farm Staff / Admin</td></tr>
            <tr><td><span class="method method-dark">POST</span></td><td><code>/api/v1/batches/{id}/dispose</code></td><td>Ghi phiếu hao hụt (<code>loss_records</code>) và trừ tồn kho lô</td><td>Farm Staff / Admin</td></tr>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/batches/{id}/loss-records</code></td><td>Lịch sử phiếu hao hụt của lô</td><td>Farm Staff / Admin</td></tr>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/batches-expiring-soon</code></td><td>Danh sách lô cận hạn &amp; tạo cảnh báo <code>batch_alerts</code></td><td>Farm Staff / Admin</td></tr>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/batch-alerts</code></td><td>Danh sách cảnh báo lô cận hạn cho Sales</td><td>Sales / Admin</td></tr>
        </tbody>
    </table>

    <h2 id="traceability">6. Nhật ký canh tác, Chứng nhận &amp; Mã QR</h2>
    <table>
        <thead><tr><th>Method</th><th>Endpoint</th><th>Mô tả</th><th>Quyền</th></tr></thead>
        <tbody>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/batches/{batchId}/farming-logs</code></td><td>Dòng thời gian nhật ký canh tác của lô</td><td>Farm Staff / Admin</td></tr>
            <tr><td><span class="method method-dark">POST</span></td><td><code>/api/v1/batches/{batchId}/farming-logs</code></td><td>Thêm mốc nhật ký canh tác</td><td>Farm Staff / Admin</td></tr>
            <tr><td><span class="method">DELETE</span></td><td><code>/api/v1/batches/{batchId}/farming-logs/{logId}</code></td><td>Xoá mốc nhật ký ghi nhầm (trong 24 giờ đầu)</td><td>Farm Staff / Admin</td></tr>
            <tr><td><span class="method">GET / POST / DELETE</span></td><td><code>/api/v1/batches/{batchId}/certificates</code></td><td>Quản lý giấy tờ kiểm định (<code>certificates</code>)</td><td>Farm Staff / Admin</td></tr>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/traceability/{code}</code></td><td>Trang truy xuất nguồn gốc công khai theo <code>ma_lo</code> / <code>qr_code</code></td><td>Public</td></tr>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/traceability/{code}/qr.svg</code></td><td>Xuất ảnh SVG mã QR truy xuất nguồn gốc</td><td>Public</td></tr>
        </tbody>
    </table>

    <h2 id="orders">7. Đặt hàng, Tính phí ship &amp; Duyệt đơn FEFO</h2>
    <table>
        <thead><tr><th>Method</th><th>Endpoint</th><th>Mô tả</th><th>Quyền</th></tr></thead>
        <tbody>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/shipping-fee</code></td><td>Tính phí vận chuyển động theo khoảng cách km</td><td>Public</td></tr>
            <tr><td><span class="method method-dark">POST</span></td><td><code>/api/v1/orders</code></td><td>Đặt hàng (hỗ trợ cả Guest và khách đăng nhập)</td><td>Optional JWT</td></tr>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/orders</code></td><td>Danh sách đơn hàng của tôi</td><td>JWT</td></tr>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/orders/{id}</code></td><td>Chi tiết đơn kèm lô đã phân bổ FEFO</td><td>Optional JWT</td></tr>
            <tr><td><span class="method method-dark">POST</span></td><td><code>/api/v1/orders/{id}/demo-confirm</code></td><td>Mô phỏng duyệt đơn chạy <code>FefoService::allocateAndDeduct()</code></td><td>Optional JWT</td></tr>
            <tr><td><span class="method method-dark">POST</span></td><td><code>/api/v1/orders/{id}/cancel</code></td><td>Khách huỷ đơn khi còn trạng thái <code>cho_duyet</code></td><td>JWT</td></tr>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/sales/orders</code></td><td>Danh sách đơn hàng cho Sales</td><td>Sales / Admin</td></tr>
            <tr><td><span class="method">PATCH</span></td><td><code>/api/v1/orders/{id}/status</code></td><td>Cập nhật trạng thái đơn (<code>da_duyet</code>, <code>dang_giao</code>, <code>hoan_thanh</code>, <code>da_huy</code>)</td><td>Sales / Admin</td></tr>
            <tr><td><span class="method method-dark">POST</span></td><td><code>/api/v1/sales/orders/{id}/confirm|ship|deliver|cancel</code></td><td>Các hành động xử lý đơn theo từng bước</td><td>Sales / Admin</td></tr>
        </tbody>
    </table>

    <h2 id="subscriptions">8. Gói rau định kỳ &amp; Lịch giao</h2>
    <table>
        <thead><tr><th>Method</th><th>Endpoint</th><th>Mô tả</th><th>Quyền</th></tr></thead>
        <tbody>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/subscription-plans</code></td><td>Danh sách gói rau định kỳ đang mở bán</td><td>Public</td></tr>
            <tr><td><span class="method method-dark">POST</span></td><td><code>/api/v1/subscriptions</code></td><td>Đăng ký gói định kỳ và tự sinh lịch giao 30 ngày</td><td>Customer</td></tr>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/my-subscriptions</code></td><td>Xem gói định kỳ &amp; lịch giao của khách</td><td>JWT</td></tr>
            <tr><td><span class="method method-dark">POST</span></td><td><code>/api/v1/subscriptions/{id}/pause</code></td><td>Tạm dừng gói</td><td>JWT</td></tr>
            <tr><td><span class="method method-dark">POST</span></td><td><code>/api/v1/sales/subscriptions/{id}/skip</code></td><td>Bỏ qua kỳ giao tiếp theo khi thiếu hàng</td><td>Sales / Admin</td></tr>
        </tbody>
    </table>

    <h2 id="payments">9. Thanh toán VNPay &amp; VietQR</h2>
    <table>
        <thead><tr><th>Method</th><th>Endpoint</th><th>Mô tả</th><th>Quyền</th></tr></thead>
        <tbody>
            <tr><td><span class="method method-dark">POST</span></td><td><code>/api/v1/vnpay/create</code></td><td>Tạo URL thanh toán VNPay Sandbox</td><td>Public</td></tr>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/vnpay/return</code> / <code>/api/v1/vnpay/ipn</code></td><td>Xác thực chữ ký &amp; tự động trừ kho FEFO</td><td>VNPay Callback</td></tr>
            <tr><td><span class="method method-dark">POST</span></td><td><code>/api/v1/payments/confirm</code></td><td>Xác nhận thanh toán VietQR &amp; kích hoạt FEFO</td><td>Optional JWT</td></tr>
            <tr><td><span class="method method-dark">POST</span></td><td><code>/api/v1/vietqr/webhook</code></td><td>Webhook đối soát biến động số dư VietQR</td><td>Webhook</td></tr>
        </tbody>
    </table>

    <h2 id="complaints">10. Khiếu nại, Đánh giá, Upload &amp; Báo cáo</h2>
    <table>
        <thead><tr><th>Method</th><th>Endpoint</th><th>Mô tả</th><th>Quyền</th></tr></thead>
        <tbody>
            <tr><td><span class="method method-dark">POST</span></td><td><code>/api/v1/complaints</code></td><td>Khách gửi khiếu nại theo <code>order_detail_id</code> kèm ảnh/video</td><td>JWT</td></tr>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/my-complaints</code></td><td>Danh sách khiếu nại của khách</td><td>JWT</td></tr>
            <tr><td><span class="method">GET / POST</span></td><td><code>/api/v1/sales/complaints</code>, <code>.../handle</code></td><td>Sales xử lý (<code>hoan_tien</code> / <code>giao_bu</code> / <code>tu_choi</code>)</td><td>Sales / Admin</td></tr>
            <tr><td><span class="method method-dark">POST</span></td><td><code>/api/v1/reviews</code></td><td>Đánh giá sản phẩm đã mua (<code>reviews</code>)</td><td>JWT</td></tr>
            <tr><td><span class="method method-dark">POST</span></td><td><code>/api/v1/upload</code></td><td>Upload ảnh/video minh chứng lên Cloudinary / local fallback</td><td>JWT</td></tr>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/admin/users</code>, <code>PUT .../role</code>, <code>POST .../toggle-status</code></td><td>Quản lý tài khoản và phân quyền</td><td>Admin</td></tr>
            <tr><td><span class="method">GET</span></td><td><code>/api/v1/admin/reports/overview|revenue|inventory|top-products</code></td><td>Báo cáo doanh thu, tồn kho, hao hụt &amp; bán chạy</td><td>Admin</td></tr>
        </tbody>
    </table>

    <div class="footer">
        <p>Tài liệu RESTful API đồng bộ 19 bảng CSDL &amp; 5 phương thức HTTP (GET, POST, PUT, PATCH, DELETE).</p>
    </div>

</div>
</body>
</html>
