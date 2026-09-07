<?php

namespace App\Services\Hrm;

use App\Models\Master\SysGeofenceLocation;

// Geofencing GPS (Modul 09) -- formula Haversine diadopsi dari
// docs/payroll_timeattendance/tm2hrm_laravel_php83.zip::GpsHaversineService,
// TAPI titik koordinat & radius dibaca dari tabel sys_geofence_location
// (data-driven, bisa nambah lokasi pabrik lain tanpa ubah kode) -- versi
// referensi aslinya hardcode const PHP.
readonly class GpsHaversineService
{
    private const EARTH_RADIUS_METERS = 6371000;

    /**
     * @return array{distance_meters: float, is_in_radius: bool, location: ?SysGeofenceLocation}
     */
    public function nearestLocation(float $latitude, float $longitude): array
    {
        $locations = SysGeofenceLocation::where('activate', true)->get();

        $best = null;
        $bestDistance = null;

        foreach ($locations as $location) {
            $distance = $this->distanceMeters($latitude, $longitude, (float) $location->latitude, (float) $location->longitude);
            if ($bestDistance === null || $distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $location;
            }
        }

        if (! $best) {
            return ['distance_meters' => 0.0, 'is_in_radius' => false, 'location' => null];
        }

        return [
            'distance_meters' => round($bestDistance, 1),
            'is_in_radius' => $bestDistance <= $best->radius_meters,
            'location' => $best,
        ];
    }

    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }
}
