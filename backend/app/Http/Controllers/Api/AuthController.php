<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ho_ten'        => ['required', 'string', 'max:100'],
            'email'         => ['required', 'email', 'max:150', 'unique:users,email'],
            'so_dien_thoai' => ['nullable', 'string', 'max:15', 'unique:users,so_dien_thoai'],
            'mat_khau'      => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'ho_ten.required'        => 'Vui lòng nhập họ tên.',
            'email.required'         => 'Vui lòng nhập email.',
            'email.email'            => 'Email không đúng định dạng.',
            'email.unique'           => 'Email này đã được đăng ký.',
            'so_dien_thoai.unique'   => 'Số điện thoại này đã được sử dụng.',
            'mat_khau.required'      => 'Vui lòng nhập mật khẩu.',
            'mat_khau.min'           => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'mat_khau.confirmed'     => 'Xác nhận mật khẩu không khớp.',
        ]);

        $customerRole = Role::firstOrCreate(
            ['ten_vai_tro' => Role::CUSTOMER],
            ['mo_ta' => 'Khách hàng']
        );

        $user = User::create([
            'role_id'       => $customerRole->id,
            'ho_ten'        => $validated['ho_ten'],
            'email'         => $validated['email'],
            'so_dien_thoai' => $validated['so_dien_thoai'] ?? null,
            'mat_khau'      => $validated['mat_khau'],
            'diem_tich_luy' => 0,
            'trang_thai'    => true,
        ]);

        $user->load('role');
        $token = Auth::guard('api')->login($user);

        return $this->success([
            'user'  => $user,
            'token' => $token,
        ], 'Đăng ký thành công.', 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'mat_khau' => ['required', 'string'],
        ], [
            'email.required'    => 'Vui lòng nhập email.',
            'mat_khau.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        $user = User::with('role')->where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['mat_khau'], $user->mat_khau)) {
            throw ValidationException::withMessages([
                'email' => 'Email hoặc mật khẩu không đúng.',
            ]);
        }

        if (! $user->trang_thai) {
            return $this->error('Tài khoản của bạn đã bị khoá.', 403);
        }

        $token = Auth::guard('api')->login($user);

        return $this->success([
            'user'  => $user,
            'token' => $token,
        ], 'Đăng nhập thành công.');
    }

    public function me(): JsonResponse
    {
        $user = Auth::guard('api')->user();
        $user?->load('role');

        return $this->success($user, 'Lấy thông tin thành công.');
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        $validated = $request->validate([
            'ho_ten'        => ['sometimes', 'string', 'max:100'],
            'so_dien_thoai' => ['sometimes', 'nullable', 'string', 'max:15', 'unique:users,so_dien_thoai,' . $user->id],
        ]);

        $user->update($validated);

        return $this->success($user->fresh('role'), 'Cập nhật thông tin thành công.');
    }

    public function logout(): JsonResponse
    {
        Auth::guard('api')->logout();

        return $this->success(null, 'Đã đăng xuất.');
    }
}
