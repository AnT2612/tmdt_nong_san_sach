<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AddressController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $addresses = Address::where('user_id', Auth::guard('api')->id())
            ->orderByDesc('mac_dinh')
            ->latest()
            ->get();

        return $this->success($addresses, 'Lấy danh sách địa chỉ thành công.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dia_chi'    => ['required', 'string', 'max:255'],
            'quan_huyen' => ['required', 'string', 'max:100'],
            'tinh_thanh' => ['required', 'string', 'max:100'],
            'vi_do'      => ['nullable', 'numeric', 'between:-90,90'],
            'kinh_do'    => ['nullable', 'numeric', 'between:-180,180'],
            'mac_dinh'   => ['boolean'],
        ]);

        $userId = Auth::guard('api')->id();
        if (! empty($validated['mac_dinh'])) {
            Address::where('user_id', $userId)->update(['mac_dinh' => false]);
        }

        $address = Address::create([
            'user_id'    => $userId,
            'dia_chi'    => $validated['dia_chi'],
            'quan_huyen' => $validated['quan_huyen'],
            'tinh_thanh' => $validated['tinh_thanh'],
            'vi_do'      => $validated['vi_do'] ?? null,
            'kinh_do'    => $validated['kinh_do'] ?? null,
            'mac_dinh'   => (bool) ($validated['mac_dinh'] ?? false),
        ]);

        return $this->success($address, 'Đã thêm địa chỉ giao hàng.', 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $address = Address::where('user_id', Auth::guard('api')->id())->find($id);
        if ($address === null) {
            return $this->error('Không tìm thấy địa chỉ.', 404);
        }

        $address->delete();

        return $this->success(null, 'Đã xoá địa chỉ.');
    }
}
