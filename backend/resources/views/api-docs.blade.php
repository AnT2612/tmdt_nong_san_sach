<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tài liệu API - Website Nông Sản Sạch</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #ffffff;
            color: #111111;
            line-height: 1.5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        h1 {
            font-size: 24px;
            border-bottom: 2px solid #000000;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        h2 {
            font-size: 18px;
            margin-top: 30px;
            border-bottom: 1px solid #999999;
            padding-bottom: 5px;
        }
        h3 {
            font-size: 15px;
            margin-top: 18px;
            margin-bottom: 6px;
        }
        a {
            color: #000000;
        }
        .info-box {
            border: 1px solid #000000;
            background-color: #f7f7f7;
            padding: 12px 15px;
            margin: 15px 0;
        }
        .menu {
            border: 1px solid #cccccc;
            padding: 10px 15px;
            background-color: #fafafa;
            margin-bottom: 20px;
        }
        .menu ul {
            margin: 5px 0 0 18px;
            padding: 0;
        }
        .menu li {
            margin-bottom: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        th, td {
            border: 1px solid #cccccc;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #eeeeee;
            font-weight: bold;
        }
        code {
            font-family: Consolas, "Courier New", monospace;
            background-color: #f2f2f2;
            padding: 1px 4px;
            border: 1px solid #dddddd;
            font-size: 13px;
        }
        pre {
            font-family: Consolas, "Courier New", monospace;
            background-color: #f4f4f4;
            border: 1px solid #cccccc;
            padding: 10px;
            overflow-x: auto;
            font-size: 13px;
            margin: 6px 0 12px 0;
        }
        .method {
            display: inline-block;
            font-family: Consolas, "Courier New", monospace;
            font-weight: bold;
            font-size: 12px;
            border: 1px solid #000000;
            padding: 2px 6px;
            background-color: #ffffff;
            color: #000000;
        }
        .method-dark {
            background-color: #000000;
            color: #ffffff;
        }
        details {
            border: 1px solid #cccccc;
            padding: 8px 12px;
            margin-bottom: 10px;
            background-color: #ffffff;
        }
        summary {
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 12px;
            border-top: 1px solid #cccccc;
            font-size: 13px;
            color: #444444;
        }
    </style>
</head>
<body>
<div class="container">

    <h1>Tài liệu API - Website Thương Mại Điện Tử Nông Sản Sạch</h1>
    <p><strong>Nhóm 8</strong> - Lớp CĐ CNTT 24WebC - Trường Cao đẳng Kỹ thuật Cao Thắng</p>

    <div class="info-box">
        <p style="margin: 0 0 6px 0;"><strong>Base URL:</strong> <code>{{ url('/api') }}</code></p>
        <p style="margin: 0 0 6px 0;"><strong>Định dạng dữ liệu:</strong> JSON (<code>Accept: application/json</code>, <code>Content-Type: application/json</code>)</p>
        <p style="margin: 0;"><strong>Xác thực:</strong> JWT Bearer Token trong Header: <code>Authorization: Bearer &lt;token&gt;</code></p>
    </div>

    <div class="menu">
        <strong>Mục lục nhanh:</strong>
        <ul>
            <li><a href="#tai-khoan-mau">1. Tài khoản mẫu &amp; Phân quyền</a></li>
            <li><a href="#cau-truc-loi">2. Cấu trúc phản hồi &amp; Bắt lỗi tập trung (Global Error Handling)</a></li>
            <li><a href="#api-auth">3. Xác thực &amp; Hồ sơ (/auth/*)</a></li>
            <li><a href="#api-products">4. Sản phẩm &amp; Danh mục (/products, /categories)</a></li>
            <li><a href="#api-batches">5. Quản lý Lô hàng &amp; Tồn kho (/batches*)</a></li>
            <li><a href="#api-traceability">6. Nhật ký canh tác &amp; Mã QR truy xuất (/farming-logs, /traceability)</a></li>
            <li><a href="#api-orders">7. Đặt hàng &amp; Duyệt đơn FEFO (/orders*, /sales/orders*)</a></li>
            <li><a href="#api-subscriptions">8. Gói rau định kỳ (/subscriptions*)</a></li>
            <li><a href="#api-payments">9. Thanh toán VNPay &amp; VietQR (/vnpay*, /payments*, /vietqr*)</a></li>
            <li><a href="#api-other">10. Khiếu nại, Upload &amp; Quản trị / Báo cáo</a></li>
        </ul>
    </div>

    <h2 id="tai-khoan-mau">1. Tài khoản mẫu &amp; Phân quyền</h2>
    <p>Sau khi chạy <code>php artisan migrate:fresh --seed</code>, hệ thống tạo sẵn 5 tài khoản đại diện cho 5 vai trò (mật khẩu chung: <code>12345678</code>):</p>
    <table>
        <thead>
            <tr>
                <th>Email</th>
                <th>Mật khẩu</th>
                <th>Vai trò (role)</th>
                <th>Chức năng chính</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>admin@nongsan.vn</code></td>
                <td><code>12345678</code></td>
                <td><code>admin</code></td>
                <td>Quản lý người dùng, xem báo cáo thống kê, toàn quyền hệ thống</td>
            </tr>
            <tr>
                <td><code>kho@nongsan.vn</code></td>
                <td><code>12345678</code></td>
                <td><code>warehouse_staff</code></td>
                <td>Thu hoạch nhập kho, xuất hủy / hao hụt, xem lô cận hạn và thẻ kho</td>
            </tr>
            <tr>
                <td><code>sales@nongsan.vn</code></td>
                <td><code>12345678</code></td>
                <td><code>sales</code></td>
                <td>Duyệt đơn (chạy thuật toán FEFO), giao hàng, xử lý khiếu nại, dời kỳ gói</td>
            </tr>
            <tr>
                <td><code>nongtrai@nongsan.vn</code></td>
                <td><code>12345678</code></td>
                <td><code>farm_staff</code></td>
                <td>Tạo lô gieo trồng mới, ghi và xoá nhật ký canh tác</td>
            </tr>
            <tr>
                <td><code>khachhang@nongsan.vn</code></td>
                <td><code>12345678</code></td>
                <td><code>customer</code></td>
                <td>Đặt hàng, tích/đổi điểm, đăng ký gói định kỳ, gửi khiếu nại</td>
            </tr>
        </tbody>
    </table>

    <h2 id="cau-truc-loi">2. Cấu trúc phản hồi &amp; Bắt lỗi tập trung (Global Error Handling)</h2>
    <p>Cấu hình tại <code>bootstrap/app.php</code> và <code>App\Http\Controllers\Concerns\ApiResponse</code> để mọi API đều trả về JSON đồng nhất thay vì trang lỗi 500 HTML.</p>

    <details open>
        <summary>Mẫu JSON Thành công (200 / 201) &amp; Phân trang</summary>
<pre>{
    "success": true,
    "message": "Lấy dữ liệu thành công.",
    "data": { ... }
}</pre>
<pre>{
    "success": true,
    "message": "Lấy danh sách thành công.",
    "data": [ ... ],
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 7
    }
}</pre>
    </details>

    <details open>
        <summary>Mẫu JSON Lỗi chuẩn (422 Validate / Thiếu hàng FEFO, 401, 403, 404)</summary>
        <p><strong>Lỗi Validate / Đặt vượt tồn kho (HTTP 422):</strong></p>
<pre>{
    "success": false,
    "message": "Dữ liệu không hợp lệ.",
    "errors": {
        "items": [
            "Cà chua bi chỉ còn 12 kg, không đủ 50 kg."
        ]
    }
}</pre>
        <p><strong>Chưa đăng nhập / Thiếu token (HTTP 401):</strong></p>
<pre>{
    "success": false,
    "message": "Bạn cần đăng nhập để thực hiện thao tác này."
}</pre>
        <p><strong>Sai vai trò phân quyền (HTTP 403):</strong></p>
<pre>{
    "success": false,
    "message": "Bạn không có quyền truy cập chức năng này.",
    "your_role": "farm_staff",
    "required_roles": ["sales", "admin"]
}</pre>
        <p><strong>Không tìm thấy đường dẫn / tài nguyên (HTTP 404):</strong></p>
<pre>{
    "success": false,
    "message": "Không tìm thấy tài nguyên yêu cầu."
}</pre>
    </details>

    <h2 id="api-auth">3. Xác thực &amp; Hồ sơ (<code>/auth/*</code>)</h2>
    <table>
        <thead>
            <tr>
                <th>Method</th>
                <th>Endpoint</th>
                <th>Quyền</th>
                <th>Mô tả</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/auth/register</code></td>
                <td>Công khai</td>
                <td>Đăng ký tài khoản khách hàng mới, trả về JWT token (201)</td>
            </tr>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/auth/login</code></td>
                <td>Công khai</td>
                <td>Đăng nhập lấy JWT token (giới hạn 5 lần/phút mỗi IP)</td>
            </tr>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/auth/me</code></td>
                <td>Đã đăng nhập</td>
                <td>Xem thông tin tài khoản hiện tại và điểm tích luỹ</td>
            </tr>
            <tr>
                <td><span class="method">PUT</span></td>
                <td><code>/api/auth/profile</code></td>
                <td>Đã đăng nhập</td>
                <td>Cập nhật <code>full_name</code>, <code>phone</code>, <code>avatar_url</code></td>
            </tr>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/auth/logout</code></td>
                <td>Đã đăng nhập</td>
                <td>Huỷ JWT token hiện tại</td>
            </tr>
        </tbody>
    </table>

    <details>
        <summary>Ví dụ Request Body: Đăng ký &amp; Đăng nhập</summary>
        <p><strong>POST /api/auth/register</strong></p>
<pre>{
    "full_name": "Nguyễn Văn A",
    "email": "nguyenvana@example.com",
    "phone": "0909123456",
    "password": "password123",
    "password_confirmation": "password123"
}</pre>
        <p><strong>POST /api/auth/login</strong></p>
<pre>{
    "email": "khachhang@nongsan.vn",
    "password": "12345678"
}</pre>
    </details>

    <h2 id="api-products">4. Sản phẩm &amp; Danh mục</h2>
    <table>
        <thead>
            <tr>
                <th>Method</th>
                <th>Endpoint</th>
                <th>Quyền</th>
                <th>Mô tả</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/products</code></td>
                <td>Công khai</td>
                <td>Danh sách sản phẩm. Hỗ trợ query: <code>category</code>, <code>min_price</code>, <code>max_price</code>, <code>certification</code>, <code>search</code>, <code>sort</code>, <code>per_page</code></td>
            </tr>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/products/{slugOrId}</code></td>
                <td>Công khai</td>
                <td>Chi tiết sản phẩm kèm tồn kho khả dụng (tổng các lô FEFO) và đánh giá</td>
            </tr>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/categories</code></td>
                <td>Công khai</td>
                <td>Danh sách danh mục sản phẩm đang hoạt động</td>
            </tr>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/available-batches</code></td>
                <td>Công khai</td>
                <td>Bảng lô đang bán ("Hôm nay xuất vườn"), nhận query <code>limit</code> (tối đa 40)</td>
            </tr>
        </tbody>
    </table>

    <h2 id="api-batches">5. Quản lý Lô hàng &amp; Tồn kho (<code>/batches*</code>)</h2>
    <table>
        <thead>
            <tr>
                <th>Method</th>
                <th>Endpoint</th>
                <th>Quyền</th>
                <th>Mô tả</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/batches</code></td>
                <td><code>farm_staff</code>, <code>warehouse_staff</code>, <code>admin</code></td>
                <td>Danh sách lô hàng sắp xếp theo FEFO. Query: <code>product_id</code>, <code>status</code>, <code>search</code>, <code>per_page</code></td>
            </tr>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/batches</code></td>
                <td><code>farm_staff</code>, <code>admin</code></td>
                <td>Tạo lô gieo trồng mới (trạng thái ban đầu: <code>growing</code>)</td>
            </tr>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/batches/{id}/harvest</code></td>
                <td><code>warehouse_staff</code>, <code>admin</code></td>
                <td>Thu hoạch nhập kho, tự tính <code>expiry_date = harvest_date + shelf_life_days</code></td>
            </tr>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/batches/{id}/dispose</code></td>
                <td><code>warehouse_staff</code>, <code>admin</code></td>
                <td>Ghi nhận xuất hủy / hao hụt (chặn nếu vượt tồn của lô, trả 422)</td>
            </tr>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/batches-expiring-soon</code></td>
                <td><code>farm_staff</code>, <code>warehouse_staff</code>, <code>admin</code></td>
                <td>Lấy các lô sắp hết hạn (query <code>days</code>, mặc định 3 ngày)</td>
            </tr>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/batches/{id}/transactions</code></td>
                <td><code>farm_staff</code>, <code>warehouse_staff</code>, <code>admin</code></td>
                <td>Xem thẻ kho (lịch sử nhập/xuất bán/xuất hủy) của một lô</td>
            </tr>
        </tbody>
    </table>

    <details>
        <summary>Ví dụ Request Body: Tạo lô, Thu hoạch, Xuất hủy</summary>
        <p><strong>POST /api/batches</strong></p>
<pre>{
    "product_id": 3,
    "planted_date": "2026-10-20",
    "quality_note": "Trồng trong nhà lưới"
}</pre>
        <p><strong>POST /api/batches/{id}/harvest</strong></p>
<pre>{
    "harvest_date": "2026-11-25",
    "quantity_imported": 15.5
}</pre>
        <p><strong>POST /api/batches/{id}/dispose</strong> (<code>reason</code>: <code>damaged</code>, <code>expired</code>, <code>overripe</code>, <code>other</code>)</p>
<pre>{
    "quantity": 1.5,
    "reason": "damaged",
    "note": "Dập nát trong quá trình phân loại"
}</pre>
    </details>

    <h2 id="api-traceability">6. Nhật ký canh tác &amp; Mã QR truy xuất</h2>
    <table>
        <thead>
            <tr>
                <th>Method</th>
                <th>Endpoint</th>
                <th>Quyền</th>
                <th>Mô tả</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/batches/{batchId}/farming-logs</code></td>
                <td><code>farm_staff</code>, <code>admin</code></td>
                <td>Xem toàn bộ dòng thời gian nhật ký của một lô</td>
            </tr>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/batches/{batchId}/farming-logs</code></td>
                <td><code>farm_staff</code>, <code>admin</code></td>
                <td>Thêm một mốc canh tác (gieo hạt, tưới nước, bón phân...)</td>
            </tr>
            <tr>
                <td><span class="method method-dark">DELETE</span></td>
                <td><code>/api/batches/{batchId}/farming-logs/{logId}</code></td>
                <td><code>farm_staff</code>, <code>admin</code></td>
                <td>Xoá mốc nhật ký ghi nhầm (chỉ cho phép trong 24 giờ đầu)</td>
            </tr>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/traceability/{token}</code></td>
                <td>Công khai</td>
                <td>Tra cứu thông tin lô + timeline canh tác theo <code>traceability_token</code> hoặc <code>batch_code</code></td>
            </tr>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/traceability/{token}/qr.svg</code></td>
                <td>Công khai</td>
                <td>Sinh ảnh mã QR định dạng <code>image/svg+xml</code> để hiển thị hoặc in tem</td>
            </tr>
        </tbody>
    </table>

    <details>
        <summary>Ví dụ Request Body: Thêm nhật ký canh tác</summary>
        <p><strong>POST /api/batches/{batchId}/farming-logs</strong> (<code>activity_type</code>: <code>planting</code>, <code>watering</code>, <code>fertilizing</code>, <code>spraying</code>, <code>inspection</code>, <code>harvesting</code>)</p>
<pre>{
    "activity_type": "watering",
    "activity_date": "2026-11-10",
    "title": "Tưới nhỏ giọt buổi sáng",
    "description": "Tưới nước giếng lọc sạch, 20 phút",
    "media": []
}</pre>
    </details>

    <h2 id="api-orders">7. Đặt hàng &amp; Duyệt đơn FEFO (<code>/orders*</code>, <code>/sales/orders*</code>)</h2>
    <table>
        <thead>
            <tr>
                <th>Method</th>
                <th>Endpoint</th>
                <th>Quyền</th>
                <th>Mô tả</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/orders</code></td>
                <td><code>jwt.optional</code> (Guest &amp; Customer)</td>
                <td>Đặt hàng mới (trạng thái <code>pending</code>). Nếu đặt vượt tổng tồn hợp lệ sẽ rollback và báo lỗi 422</td>
            </tr>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/orders</code></td>
                <td>Đã đăng nhập</td>
                <td>Danh sách đơn hàng của chính khách</td>
            </tr>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/orders/{id}</code></td>
                <td><code>jwt.optional</code></td>
                <td>Chi tiết đơn kèm <code>batch_allocations</code> (bằng chứng FEFO)</td>
            </tr>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/orders/{id}/cancel</code></td>
                <td>Đã đăng nhập</td>
                <td>Khách tự huỷ đơn khi còn trạng thái <code>pending</code></td>
            </tr>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/orders/{id}/demo-confirm</code></td>
                <td><code>jwt.optional</code></td>
                <td>Mô phỏng Sales duyệt đơn chạy FEFO cho mục đích demo</td>
            </tr>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/sales/orders</code></td>
                <td><code>sales</code>, <code>admin</code></td>
                <td>Danh sách đơn cho Sales lọc theo <code>status</code>, <code>search</code></td>
            </tr>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/sales/orders/{id}/confirm</code></td>
                <td><code>sales</code>, <code>admin</code></td>
                <td><strong>Duyệt đơn &amp; chạy thuật toán FEFO</strong> tách trừ nhiều lô theo hạn gần nhất</td>
            </tr>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/sales/orders/{id}/ship</code></td>
                <td><code>sales</code>, <code>admin</code></td>
                <td>Chuyển đơn sang trạng thái đang giao (<code>shipping</code>)</td>
            </tr>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/sales/orders/{id}/deliver</code></td>
                <td><code>sales</code>, <code>admin</code></td>
                <td>Xác nhận giao thành công, cộng điểm tích luỹ và đánh dấu COD đã thu tiền</td>
            </tr>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/sales/orders/{id}/cancel</code></td>
                <td><code>sales</code>, <code>admin</code></td>
                <td>Sales huỷ đơn (tự động hoàn hàng về đúng lô cũ nếu đã trừ kho)</td>
            </tr>
        </tbody>
    </table>

    <details>
        <summary>Ví dụ Request Body: Đặt hàng &amp; Huỷ đơn</summary>
        <p><strong>POST /api/orders</strong> (<code>payment_method</code>: <code>cod</code>, <code>vnpay</code>, <code>vietqr</code>)</p>
<pre>{
    "receiver_name": "Đào Quốc Đạt",
    "receiver_phone": "0901234565",
    "shipping_address": "123 Lê Lợi, Quận 1, TP.HCM",
    "note": "Giao buổi sáng",
    "payment_method": "cod",
    "points_used": 0,
    "items": [
        { "product_id": 3, "quantity": 5 }
    ]
}</pre>
        <p><strong>POST /api/orders/{id}/cancel</strong> hoặc <strong>POST /api/sales/orders/{id}/cancel</strong></p>
<pre>{
    "reason": "Khách đổi lịch nhận hàng"
}</pre>
    </details>

    <h2 id="api-subscriptions">8. Gói rau định kỳ (<code>/subscriptions*</code>)</h2>
    <table>
        <thead>
            <tr>
                <th>Method</th>
                <th>Endpoint</th>
                <th>Quyền</th>
                <th>Mô tả</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/subscription-plans</code></td>
                <td>Công khai</td>
                <td>Danh sách các gói rau định kỳ đang mở bán</td>
            </tr>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/subscriptions</code></td>
                <td><code>customer</code></td>
                <td>Khách đăng ký một gói định kỳ</td>
            </tr>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/my-subscriptions</code></td>
                <td>Đã đăng nhập</td>
                <td>Danh sách gói định kỳ của chính khách</td>
            </tr>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/subscriptions/{id}/pause</code></td>
                <td>Đã đăng nhập</td>
                <td>Tạm dừng gói đang hoạt động</td>
            </tr>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/sales/subscriptions/{id}/skip</code></td>
                <td><code>sales</code>, <code>admin</code></td>
                <td>Sales dời kỳ giao sang lần kế tiếp khi nông trại thiếu hàng</td>
            </tr>
        </tbody>
    </table>

    <h2 id="api-payments">9. Thanh toán VNPay &amp; VietQR</h2>
    <table>
        <thead>
            <tr>
                <th>Method</th>
                <th>Endpoint</th>
                <th>Quyền</th>
                <th>Mô tả</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/payments/confirm</code></td>
                <td><code>jwt.optional</code></td>
                <td>Xác nhận thanh toán QR trực tiếp (body: <code>{"order_id": 1}</code>)</td>
            </tr>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/vnpay/create</code></td>
                <td>Công khai</td>
                <td>Tạo URL thanh toán VNPay Sandbox (alias: <code>/api/payments/vnpay/create</code>)</td>
            </tr>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/vnpay/return</code></td>
                <td>Công khai</td>
                <td>Callback trình duyệt sau khi khách thanh toán trên VNPay (alias: <code>/api/payments/vnpay/return</code>)</td>
            </tr>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/vnpay/ipn</code></td>
                <td>Công khai</td>
                <td>Webhook IPN server-to-server từ VNPay (alias: <code>/api/payments/vnpay/ipn</code>)</td>
            </tr>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/vietqr/webhook</code></td>
                <td>Công khai</td>
                <td>Webhook tự động đối soát biến động số dư VietQR/SePay (alias: <code>/api/sepay/webhook</code>)</td>
            </tr>
        </tbody>
    </table>

    <h2 id="api-other">10. Khiếu nại, Upload &amp; Quản trị / Báo cáo</h2>
    <table>
        <thead>
            <tr>
                <th>Method</th>
                <th>Endpoint</th>
                <th>Quyền</th>
                <th>Mô tả</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/upload</code></td>
                <td>Đã đăng nhập</td>
                <td>Upload tệp <code>file</code> (ảnh JPG/PNG/WEBP &le; 5MB, video MP4/MOV &le; 30MB)</td>
            </tr>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/complaints</code></td>
                <td>Đã đăng nhập</td>
                <td>Gửi khiếu nại đơn đã giao trong vòng 24h (bắt buộc có mảng <code>evidence_media</code>)</td>
            </tr>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/my-complaints</code></td>
                <td>Đã đăng nhập</td>
                <td>Danh sách khiếu nại của chính khách</td>
            </tr>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/sales/complaints</code></td>
                <td><code>sales</code>, <code>admin</code></td>
                <td>Danh sách khiếu nại cần Sales xử lý</td>
            </tr>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/sales/complaints/{id}/handle</code></td>
                <td><code>sales</code>, <code>admin</code></td>
                <td>Duyệt hoàn tiền / giao bù / từ chối khiếu nại</td>
            </tr>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/admin/users</code></td>
                <td><code>admin</code></td>
                <td>Danh sách tài khoản người dùng (lọc theo <code>role</code>, <code>search</code>)</td>
            </tr>
            <tr>
                <td><span class="method">PUT</span></td>
                <td><code>/api/admin/users/{id}/role</code></td>
                <td><code>admin</code></td>
                <td>Đổi vai trò của người dùng (<code>{"role": "sales"}</code>)</td>
            </tr>
            <tr>
                <td><span class="method">POST</span></td>
                <td><code>/api/admin/users/{id}/toggle-status</code></td>
                <td><code>admin</code></td>
                <td>Khoá / mở khoá tài khoản người dùng</td>
            </tr>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/admin/reports/overview</code></td>
                <td><code>admin</code></td>
                <td>Báo cáo tổng quan: doanh thu, đơn hàng, khách hàng, tỷ lệ hao hụt</td>
            </tr>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/admin/reports/revenue</code></td>
                <td><code>admin</code></td>
                <td>Doanh thu theo từng ngày (query: <code>from</code>, <code>to</code>)</td>
            </tr>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/admin/reports/inventory</code></td>
                <td><code>admin</code></td>
                <td>Tồn kho hiện tại theo sản phẩm và danh sách lô cận hạn</td>
            </tr>
            <tr>
                <td><span class="method">GET</span></td>
                <td><code>/api/admin/reports/top-products</code></td>
                <td><code>admin</code></td>
                <td>Danh sách sản phẩm bán chạy nhất (query: <code>limit</code>)</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>Tài liệu API nội bộ - Đồ án môn học Website Thương mại điện tử Nông sản sạch (Nhóm 8).</p>
    </div>

</div>
</body>
</html>
