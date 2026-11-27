#Nông Sản Sạch — Website TMĐT nông sản
Đồ án môn học · Nhóm 8 · CĐ CNTT 24WebC · Trường Cao đẳng Kỹ thuật Cao Thắng

Nền tảng thương mại điện tử khép kín cho một nông trại: bán rau củ tươi trực tiếp đến người tiêu dùng TP.HCM

#Thành Viên
Tên thành viên: Trần Quốc Khánh   | MSSV: 0306241206 \
Tên thành viên: Nguyễn Văn Đoàn   | MSSV: 0306241191 \
Tên thành viên: Trần Thanh An     | MSSV: 0306241171 \
Tên thành viên: Nguyễn Thanh Liêm | MSSV: 0306241214 \
Tên thành viên: Đào Quốc Đạt      | MSSV: 0306241190 

#Công nghệ
Lớp	Công nghệ
Backend	Laravel 12 (PHP 8.2+) · REST API · JWT
Frontend	React 19 · Vite · React Router
Database	MySQL 8.4 (utf8mb4)
Thanh toán	VNPay sandbox (có chế độ mô phỏng khi chưa có API key)
Lưu trữ ảnh	Cloudinary (nhận URL, upload chưa nối)
#Chạy nhanh
Backend

cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
Sửa .env cho khớp MySQL của bạn:

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nongsan_sach
DB_USERNAME=root
DB_PASSWORD=
Rồi:

php artisan migrate:fresh --seed
php artisan serve
Frontend

cd frontend
npm install
npm run dev
Mở http://localhost:5173.

#Tài khoản mẫu (mật khẩu 12345678)
Email	Vai trò
admin@nongsan.vn	Quản trị viên
kho@nongsan.vn	Nhân viên kho
sales@nongsan.vn	Nhân viên bán hàng
nongtrai@nongsan.vn	Nhân viên nông trại
khachhang@nongsan.vn	Khách hàng


