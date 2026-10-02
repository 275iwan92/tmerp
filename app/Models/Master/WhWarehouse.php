<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Dulu MWarehouse (tabel m_warehouse) -- direname 2026-08-27, prefix domain
// baru `wh_` (dipakai lintas modul Sales/Procurement/Inventory, tetap di
// tm2mst). Lihat migration 2026_08_27_000011.
// Sejak 2026-10-02 satu gudang = satu GEDUNG nyata (user: "gedung itu pasti terpisah, A5, A6, A10 dan masih gedung yang
// lain"): ukuran dalam meter, bentuk (kotak / huruf L / garis luar sendiri) dan 1-3 lantai; fungsi dan kelas stok ada di
// area (wh_zone).
class WhWarehouse extends Model
{
    public const SHAPES = ['RECT' => 'Rectangle', 'L' => 'L shape', 'CUSTOM' => 'Custom outline'];

    public const CORNERS = ['NE' => 'Top right', 'NW' => 'Top left', 'SE' => 'Bottom right', 'SW' => 'Bottom left'];

    public const MAX_FLOORS = 3;

    protected $connection = 'tm2mst';

    protected $table = 'wh_warehouse';

    protected $primaryKey = 'id_warehouse';

    protected $fillable = [
        'warehouse_code', 'warehouse_name', 'warehouse_type', 'activate',
        'length_m', 'width_m', 'floor_count', 'shape', 'notch_length_m', 'notch_width_m', 'notch_corner', 'outline', 'address',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean', 'length_m' => 'float', 'width_m' => 'float', 'floor_count' => 'integer',
        'notch_length_m' => 'float', 'notch_width_m' => 'float',
    ];

    public function locations(): HasMany
    {
        return $this->hasMany(WhLoc::class, 'warehouse_id', 'id_warehouse')->orderBy('row_code')->orderBy('col_code')->orderBy('stack_code');
    }

    public function zones(): HasMany
    {
        return $this->hasMany(WhZone::class, 'warehouse_id', 'id_warehouse')->orderBy('floor_no')->orderBy('sort_order')->orderBy('zone_code');
    }

    public function floors(): int
    {
        return max(1, min(self::MAX_FLOORS, (int) ($this->floor_count ?: 1)));
    }

    public function hasLayout(): bool
    {
        return $this->outlinePoints() !== [];
    }

    // Garis luar gedung sebagai titik-titik [x, y] dalam meter (x ke kanan, y ke bawah, mulai sudut kiri-atas).
    // RECT = empat sudut; L = kotak dengan satu sudut dipotong; CUSTOM = daftar titik yang diketik user.
    /** @return list<array{0: float, 1: float}> */
    public function outlinePoints(): array
    {
        if ($this->shape === 'CUSTOM') {
            $pts = [];
            foreach (preg_split('/[;\n]+/', (string) $this->outline) as $pair) {
                if (preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $pair, $m)) {
                    $pts[] = [(float) $m[1], (float) $m[2]];
                }
            }

            return count($pts) >= 3 ? $pts : [];
        }
        $l = (float) $this->length_m;
        $w = (float) $this->width_m;
        if ($l <= 0 || $w <= 0) {
            return [];
        }
        $nl = min(max((float) $this->notch_length_m, 0), $l);
        $nw = min(max((float) $this->notch_width_m, 0), $w);
        if ($this->shape !== 'L' || $nl <= 0 || $nw <= 0 || $nl >= $l || $nw >= $w) {
            return [[0, 0], [$l, 0], [$l, $w], [0, $w]];
        }

        return match ($this->notch_corner ?: 'NE') {
            'NW' => [[$nl, 0], [$l, 0], [$l, $w], [0, $w], [0, $nw], [$nl, $nw]],
            'SE' => [[0, 0], [$l, 0], [$l, $w - $nw], [$l - $nl, $w - $nw], [$l - $nl, $w], [0, $w]],
            'SW' => [[0, 0], [$l, 0], [$l, $w], [$nl, $w], [$nl, $w - $nw], [0, $w - $nw]],
            default => [[0, 0], [$l - $nl, 0], [$l - $nl, $nw], [$l, $nw], [$l, $w], [0, $w]],
        };
    }

    // Ukuran kotak pembungkus garis luar (meter) = ukuran kanvas peta.
    /** @return array{0: float, 1: float} */
    public function extent(): array
    {
        $pts = $this->outlinePoints();

        return $pts ? [max(array_column($pts, 0)), max(array_column($pts, 1))] : [0.0, 0.0];
    }

    // Luas lantai (m2) dari garis luar (rumus shoelace), per lantai.
    public function floorArea(): float
    {
        $pts = $this->outlinePoints();
        $sum = 0.0;
        foreach ($pts as $i => [$x, $y]) {
            [$nx, $ny] = $pts[($i + 1) % count($pts)];
            $sum += $x * $ny - $nx * $y;
        }

        return round(abs($sum) / 2, 2);
    }
}
