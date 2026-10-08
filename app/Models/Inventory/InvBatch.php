<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Master batch (lot) v2.01 (user 2026-10-08): satu batch = sekelompok unit dengan asal dan mutu yang sama untuk satu item.
// Status batch menentukan apakah stoknya dihitung Available dan boleh dialokasikan (booking, pick list):
//   QUARANTINE / REJECTED / BLOCKED = tidak; CONDITIONAL (URGENT atau menunggu sampling) = ya, bertanda; RELEASED = ya.
// Palet (inv_pallet.batch_id) adalah satuan fisik berlabel yang menautkan ke batch; kedaluwarsa batch = nilai awal palet.
// Kartu stok dan baris dokumen menautkan batch lewat batch_id; kolom lot_no lama tetap ada. Dibuat / diubah lewat App\Services\Inventory\BatchService.
class InvBatch extends Model
{
    public const STATUSES = [
        'QUARANTINE' => ['label' => 'Quarantine', 'tone' => 'info', 'available' => false, 'allocatable' => false],
        'CONDITIONAL' => ['label' => 'Conditional', 'tone' => 'warn', 'available' => true, 'allocatable' => true],
        'RELEASED' => ['label' => 'Released', 'tone' => 'success', 'available' => true, 'allocatable' => true],
        'REJECTED' => ['label' => 'Rejected', 'tone' => 'danger', 'available' => false, 'allocatable' => false],
        'BLOCKED' => ['label' => 'Blocked', 'tone' => 'neutral', 'available' => false, 'allocatable' => false],
    ];

    public const CERTIFICATIONS = ['NONE' => 'None', 'FSC' => 'FSC', 'PEFC' => 'PEFC', 'OTHER' => 'Other'];

    public const GRAINS = ['LONG' => 'Long grain', 'SHORT' => 'Short grain'];

    protected $connection = 'tm2inv';

    protected $table = 'inv_batch';

    protected $primaryKey = 'id_batch';

    protected $guarded = ['id_batch'];

    protected $casts = ['production_date' => 'date', 'expiry_date' => 'date', 'retest_date' => 'date', 'qc_status_at' => 'datetime', 'food_grade' => 'boolean', 'activate' => 'boolean'];

    public function pallets(): HasMany
    {
        return $this->hasMany(InvPallet::class, 'batch_id', 'id_batch');
    }

    public function isAvailable(): bool
    {
        return self::STATUSES[$this->qc_status]['available'] ?? false;
    }

    public function isAllocatable(): bool
    {
        return self::STATUSES[$this->qc_status]['allocatable'] ?? false;
    }

    // EXPIRED | SOON | null menurut tanggal kedaluwarsa dan batas "segera" (hari), sama dengan InvPallet::expiryState.
    public function expiryState(int $soonDays): ?string
    {
        if (! $this->expiry_date) {
            return null;
        }
        $days = (int) now()->startOfDay()->diffInDays($this->expiry_date->copy()->startOfDay(), false);

        return $days < 0 ? 'EXPIRED' : ($days <= $soonDays ? 'SOON' : null);
    }

    // Nomor batch: BT + 2 digit tahun + 5 digit urut (BT2600031).
    public static function nextNo(): string
    {
        $prefix = 'BT'.now()->format('y');
        $last = static::query()->where('batch_no', 'like', $prefix.'%')->orderByDesc('batch_no')->value('batch_no');

        return $prefix.str_pad((string) (($last ? (int) substr($last, strlen($prefix)) : 0) + 1), 5, '0', STR_PAD_LEFT);
    }
}
