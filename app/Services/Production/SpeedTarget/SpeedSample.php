<?php

namespace App\Services\Production\SpeedTarget;

// Satu catatan produksi (qty bagus dalam rentang menit) — dari WO v2 atau tm2old.prd_output.
final class SpeedSample
{
    public readonly ?string $uom;

    public function __construct(
        public readonly float $qty,
        public readonly float $minutes,
        public readonly ?string $process,
        ?string $uom,
        public readonly ?string $date = null,
    ) {
        $this->uom = self::normalizeUom($uom);
    }

    // Sistem lama menulis LBR, v2 menulis LEMBAR untuk satuan yang sama.
    public static function normalizeUom(?string $uom): ?string
    {
        $uom = $uom === null ? null : mb_strtoupper(trim($uom));

        return match ($uom) {
            'LBR', 'LEMBAR', 'SHEET' => 'LEMBAR',
            'PC', 'PCS' => 'PCS',
            '', null => null,
            default => $uom,
        };
    }

    public function ratePerHour(): float
    {
        return $this->qty / ($this->minutes / 60);
    }
}
