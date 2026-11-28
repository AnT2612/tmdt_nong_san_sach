<?php

namespace App\Services;

class ShippingService
{
    private const FARM_LAT = 11.0067;
    private const FARM_LNG = 106.4944;

    private const BASE_FEE = 15000;
    private const FEE_PER_KM = 5000;
    private const MAX_DISTANCE_KM = 25.0;
    private const FREE_DISTANCE_KM = 3.0;
    private const FREE_SHIPPING_THRESHOLD = 300000;

    private const DISTRICT_DISTANCE_MAP = [
        'củ chi'     => 8.0,
        'hóc môn'    => 12.0,
        'quận 12'    => 15.0,
        'gò vấp'     => 19.0,
        'tân phú'    => 19.0,
        'bình thạnh' => 22.0,
        'tân bình'   => 22.0,
        'quận 10'    => 23.0,
        'phú nhuận'  => 24.0,
        'quận 3'     => 24.5,
        'quận 1'     => 25.0,
    ];

    public function calculate(
        string $shippingAddress,
        float $subtotal,
        string $deliveryType = 'thuong',
        ?float $lat = null,
        ?float $lng = null
    ): array {
        $distanceKm = ($lat !== null && $lng !== null)
            ? $this->haversineKm(self::FARM_LAT, self::FARM_LNG, $lat, $lng) * 1.25
            : $this->estimateDistanceKm($shippingAddress);

        $distanceKm = round($distanceKm, 2);

        if ($subtotal >= self::FREE_SHIPPING_THRESHOLD && $deliveryType !== 'hoa_toc') {
            return ['khoang_cach_km' => $distanceKm, 'phi_van_chuyen' => 0.0];
        }

        if ($distanceKm <= self::FREE_DISTANCE_KM) {
            $fee = $deliveryType === 'hoa_toc' ? 15000.0 : 0.0;
            return ['khoang_cach_km' => $distanceKm, 'phi_van_chuyen' => $fee];
        }

        $cappedKm = min($distanceKm, self::MAX_DISTANCE_KM);
        $fee = ceil($cappedKm) * self::FEE_PER_KM;
        if ($deliveryType === 'hoa_toc') {
            $fee += self::BASE_FEE;
        }

        return ['khoang_cach_km' => $distanceKm, 'phi_van_chuyen' => (float) $fee];
    }

    public function estimateDistanceKm(string $shippingAddress): float
    {
        $normalized = mb_strtolower($shippingAddress);

        foreach (self::DISTRICT_DISTANCE_MAP as $district => $km) {
            if (str_contains($normalized, $district)) {
                return $km;
            }
        }

        return 12.0;
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * (2 * asin(min(1.0, sqrt($a))));
    }
}
