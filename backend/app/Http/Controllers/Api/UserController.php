<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with('role')
            ->when($request->filled('role'), function ($query) use ($request) {
                $role = $request->input('role');
                $query->whereHas('role', fn ($q) => $q->where('ten_vai_tro', $role));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $keyword = $request->string('search')->toString();

                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->where('ho_ten', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%")
                        ->orWhere('so_dien_thoai', 'like', "%{$keyword}%");
                });
            })
            ->latest()
            ->paginate(max(1, (int) $request->input('per_page', 20)));

        return $this->paginated($users, 'Lấy danh sách người dùng thành công.');
    }

    public function updateRole(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'ten_vai_tro' => ['required', 'in:customer,farm_staff,sales,admin'],
        ], [
            'ten_vai_tro.required' => 'Vui lòng chọn vai trò mới.',
            'ten_vai_tro.in'       => 'Vai trò không hợp lệ.',
        ]);

        $user = User::find($id);
        if ($user === null) {
            return $this->error('Không tìm thấy người dùng.', 404);
        }

        if ($user->id === Auth::guard('api')->id()) {
            return $this->error('Bạn không thể tự đổi vai trò của chính mình.', 422);
        }

        $role = Role::where('ten_vai_tro', $validated['ten_vai_tro'])->firstOrFail();
        $user->role_id = $role->id;
        $user->save();

        return $this->success($user->fresh('role'), 'Đã cập nhật vai trò người dùng.');
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $user = User::find($id);
        if ($user === null) {
            return $this->error('Không tìm thấy người dùng.', 404);
        }

        if ($user->id === Auth::guard('api')->id()) {
            return $this->error('Bạn không thể tự khoá tài khoản của chính mình.', 422);
        }

        $user->trang_thai = ! $user->trang_thai;
        $user->save();

        return $this->success($user->fresh('role'), 'Đã cập nhật trạng thái tài khoản.');
    }
}
