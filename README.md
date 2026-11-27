# Nông Sản Sạch — Website TMĐT nông sản

> **Đồ án môn học · Nhóm 8 · CĐ CNTT 24WebC · Trường Cao đẳng Kỹ thuật Cao Thắng**

Nền tảng thương mại điện tử khép kín cho một nông trại: bán rau củ tươi trực tiếp đến người tiêu dùng TP.HCM.

## Thành viên thực hiện

| STT | Họ và tên | MSSV |
| :-: | :--- | :--- |
| 1 | Trần Quốc Khánh | `0306241206` |
| 2 | Nguyễn Văn Đoàn | `0306241191` |
| 3 | Trần Thanh An | `0306241171` |
| 4 | Nguyễn Thanh Liêm | `0306241214` |
| 5 | Đào Quốc Đạt | `0306241190` |

## Công nghệ sử dụng

| Lớp | Công nghệ |
| :--- | :--- |
| **Backend** | Laravel 12 (PHP 8.2+) · REST API · JWT |
| **Frontend** | React 19 · Vite · React Router |
| **Database** | MySQL 8.4 (`utf8mb4`) |
| **Thanh toán** | VNPay sandbox *(có chế độ mô phỏng khi chưa có API key)* |
| **Lưu trữ ảnh** | Cloudinary *(nhận URL, chưa nối luồng upload trực tiếp)* |

## Hướng dẫn chạy nhanh

### 1. Backend

Di chuyển vào thư mục `backend`, cài đặt thư viện và khởi tạo file cấu hình môi trường:

cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

Mở file .env và cập nhật thông tin kết nối MySQL

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nongsan_sach
DB_USERNAME=root
DB_PASSWORD=

php artisan migrate:fresh --seed
php artisan serve

### 2. Frontend

Mở một cửa sổ terminal mới, di chuyển vào thư mục `frontend` và khởi chạy ứng dụng:

cd frontend
npm install
npm run dev

## Tài khoản mẫu

| STT | Vai trò | Email | Mật khẩu | Phạm vi truy cập chính |
| :-: | :--- | :--- | :---: | :--- |
| 1 | **Quản trị viên (Admin)** | `admin@nongsan.vn` | `12345678` | Quản lý hệ thống, người dùng, danh mục, sản phẩm và báo cáo tổng hợp |
| 2 | **Nhân viên kho** | `kho@nongsan.vn` | `12345678` | Quản lý nhập/xuất kho, theo dõi tồn kho lô hàng (`batches`) và xuất kho FEFO |
| 3 | **Nhân viên bán hàng** | `sales@nongsan.vn` | `12345678` | Xử lý đơn hàng, gói đăng ký định kỳ và giải quyết khiếu nại khách hàng |
| 4 | **Nhân viên nông trại** | `nongtrai@nongsan.vn` | `12345678` | Cập nhật nhật ký canh tác, thu hoạch lô mới và quản lý truy xuất nguồn gốc QR |
| 5 | **Khách hàng** | `khachhang@nongsan.vn` | `12345678` | Mua sắm nông sản, đăng ký gói giao
