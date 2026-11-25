<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    // Đăng ký tài khoản khách hàng
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:100'],
            'email'     => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'password'  => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'full_name.required' => 'Vui lòng nhập họ tên.',
            'email.required'     => 'Vui lòng nhập email.',
            'email.email'        => 'Email không đúng định dạng.',
            'email.unique'       => 'Email này đã được đăng ký.',
            'password.required'  => 'Vui lòng nhập mật khẩu.',
            'password.min'       => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);

        // Người tự đăng ký luôn là khách hàng, không thể tự nâng quyền.
        $user = User::create([
            'full_name' => $validated['full_name'],
            'email'     => $validated['email'],
            'phone'     => $validated['phone'] ?? null,
            'password'  => $validated['password'],
            'role'      => User::ROLE_CUSTOMER,
        ]);

        $token = Auth::guard('api')->login($user);

        return $this->success([
            'user'  => $user,
            'token' => $token,
        ], 'Đăng ký thành công.', 201);
    }

    // Đăng nhập
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required'    => 'Vui lòng nhập email.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        $token = Auth::guard('api')->attempt($credentials);

        if ($token === false) {
            throw ValidationException::withMessages([
                'email' => 'Email hoặc mật khẩu không đúng.',
            ]);
        }

        /** @var User $user */
        $user = Auth::guard('api')->user();

        if ($user->status === 'locked') {
            Auth::guard('api')->logout();

            return $this->error('Tài khoản của bạn đã bị khoá.', 403);
        }

        return $this->success([
            'user'  => $user,
            'token' => $token,
        ], 'Đăng nhập thành công.');
    }

    // Thông tin người đang đăng nhập
    public function me(): JsonResponse
    {
        return $this->success(Auth::guard('api')->user(), 'Lấy thông tin thành công.');
    }

    // Cập nhật thông tin cá nhân
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        $validated = $request->validate([
            'full_name'  => ['sometimes', 'string', 'max:100'],
            'phone'      => ['sometimes', 'nullable', 'string', 'max:20'],
            'avatar_url' => ['sometimes', 'nullable', 'url', 'max:500'],
        ]);

        $user->update($validated);

        return $this->success($user->fresh(), 'Cập nhật thông tin thành công.');
    }

    // Đăng xuất - huỷ token hiện tại
    public function logout(): JsonResponse
    {
        Auth::guard('api')->logout();

        return $this->success(null, 'Đã đăng xuất.');
    }
}
