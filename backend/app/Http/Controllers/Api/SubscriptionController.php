<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SubscriptionService $subscriptionService,
    ) {
    }

    public function plans(): JsonResponse
    {
        $plans = collect(config('subscriptions.plans', []))->map(function (array $plan) {
            $items = collect($plan['items'])->map(function (array $line) {
                $product = Product::where('sku', $line['sku'])->first();
                return [
                    'product_id'   => $product?->id,
                    'ten_san_pham' => $product?->ten_san_pham ?? $line['sku'],
                    'don_vi_tinh'  => $product?->don_vi_tinh ?? 'kg',
                    'so_luong'     => (float) $line['so_luong'],
                ];
            })->all();

            return array_merge($plan, ['items' => $items]);
        });

        return $this->success($plans, 'Lấy danh sách gói định kỳ thành công.');
    }

    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_code'            => ['required', 'string'],
            'ngay_bat_dau'         => ['nullable', 'date', 'after_or_equal:today'],
            'ngay_giao_trong_tuan' => ['nullable', 'string', 'max:20'],
            'dia_chi_giao'         => ['required', 'string', 'max:255'],
            'khung_gio'            => ['nullable', 'string', 'max:30'],
            'cong_thanh_toan'      => ['nullable', 'in:cod,momo,vnpay,vietqr'],
        ], [
            'plan_code.required'    => 'Vui lòng chọn gói định kỳ.',
            'dia_chi_giao.required' => 'Vui lòng nhập địa chỉ giao hàng.',
        ]);

        $planConfig = collect(config('subscriptions.plans', []))
            ->firstWhere('code', $validated['plan_code']);

        if ($planConfig === null) {
            return $this->error('Không tìm thấy gói định kỳ đã chọn.', 404);
        }

        $subscription = $this->subscriptionService->subscribe(
            Auth::guard('api')->user(),
            $planConfig,
            $validated,
        );

        return $this->success(['subscription' => $subscription], 'Đăng ký gói định kỳ thành công.', 201);
    }

    public function mySubscriptions(): JsonResponse
    {
        $subscriptions = Subscription::query()
            ->with(['items.product:id,ten_san_pham,don_vi_tinh', 'deliverySchedules', 'orders:id,ma_don,trang_thai,ngay_tao,subscription_id'])
            ->where('user_id', Auth::guard('api')->id())
            ->latest()
            ->get();

        return $this->success($subscriptions, 'Lấy danh sách gói của bạn thành công.');
    }

    public function pause(int $id): JsonResponse
    {
        $subscription = Subscription::where('user_id', Auth::guard('api')->id())->find($id);
        if ($subscription === null) {
            return $this->error('Không tìm thấy gói định kỳ.', 404);
        }

        if ($subscription->trang_thai !== Subscription::STATUS_ACTIVE) {
            return $this->error('Chỉ có thể tạm dừng gói đang hoạt động.', 422);
        }

        $subscription->trang_thai = Subscription::STATUS_PAUSED;
        $subscription->save();

        return $this->success($subscription, 'Đã tạm dừng gói định kỳ.');
    }

    public function skip(int $id): JsonResponse
    {
        $subscription = Subscription::find($id);
        if ($subscription === null) {
            return $this->error('Không tìm thấy gói định kỳ.', 404);
        }

        $schedule = $this->subscriptionService->skipSchedule($subscription);

        return $this->success($schedule, 'Đã bỏ qua kỳ giao tiếp theo.');
    }
}
