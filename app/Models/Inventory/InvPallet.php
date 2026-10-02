<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Satu palet berlabel (user 2026-10-02: label wajib dibuat setiap kedatangan dan setiap habis pemakaian): item, lot,
// kode palet dari supplier, tanggal datang, kedaluwarsa, qty saat datang dan sisa sekarang, bin sekarang.
// Saldo stok tetap di kartu stok (per item per bin); palet = satuan fisik berlabel di dalam saldo itu. Selisih antara
// saldo dan jumlah qty palet di satu bin = pekerjaan label (App\Services\Inventory\PalletService::gaps).
class InvPallet extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'inv_pallet';

    protected $primaryKey = 'id_pallet';

    protected $guarded = ['id_pallet'];

    protected $casts = ['qty_initial' => 'float', 'qty' => 'float', 'arrived_at' => 'date', 'expiry_date' => 'date', 'last_label_at' => 'datetime', 'label_count' => 'integer'];

    public function logs(): HasMany
    {
        return $this->hasMany(InvPalletLog::class, 'pallet_id', 'id_pallet')->orderByDesc('id_pallet_log');
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    // Kunci posisi stok palet ini (sama dengan WarehouseStock::key).
    public function positionKey(): string
    {
        return $this->stock_class.':'.$this->item_id.':'.(int) $this->part_id.':'.$this->location_id;
    }

    // EXPIRED | SOON | null menurut tanggal kedaluwarsa dan batas "segera" (hari).
    public function expiryState(int $soonDays): ?string
    {
        if (! $this->expiry_date) {
            return null;
        }
        $days = (int) now()->startOfDay()->diffInDays($this->expiry_date->copy()->startOfDay(), false);

        return $days < 0 ? 'EXPIRED' : ($days <= $soonDays ? 'SOON' : null);
    }

    public static function nextNo(): string
    {
        $prefix = 'PL'.now()->format('y');
        $last = static::where('pallet_no', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('pallet_no')->value('pallet_no');

        return $prefix.str_pad((string) ($last ? ((int) substr($last, 4)) + 1 : 1), 5, '0', STR_PAD_LEFT);
    }
}
