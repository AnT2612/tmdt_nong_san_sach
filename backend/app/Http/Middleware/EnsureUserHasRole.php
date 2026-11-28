<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'status'  => 'error',
                'success' => false,
                'message' => 'Bạn cần đăng nhập để thực hiện thao tác này.',
            ], 401);
        }

        $user->loadMissing('role');

        if (! $user->hasRole(...$roles)) {
            return response()->json([
                'status'         => 'error',
                'success'        => false,
                'message'        => 'Bạn không có quyền truy cập chức năng này.',
                'your_role'      => $user->ten_vai_tro,
                'required_roles' => $roles,
            ], 403);
        }

        if (! $user->trang_thai) {
            return response()->json([
                'status'  => 'error',
                'success' => false,
                'message' => 'Tài khoản của bạn đã bị khoá. Vui lòng liên hệ quản trị viên.',
            ], 403);
        }

        return $next($request);
    }
}
