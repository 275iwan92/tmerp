<?php

namespace App\Models\Inventory;

use App\Models\Master\WhWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Stock Transfer: pindah stok antar bin / antar gudang (RM, SFG, FG dalam satu dokumen). Draft -> Confirmed (stok pindah,
// nomor lahir) -> Cancelled (stok dikembalikan, alasan wajib). Kartu stok hanya ditulis App\Services\Inventory\StockTransferService.
class InvTransfer extends Model
{
    public const REASONS = ['REPLENISH' => 'Replenish a storage area', 'CONSOLIDATE' => 'Consolidate / tidy up', 'TO_PRODUCTION' => 'Bring to production',
        'DEAD_STOCK' => 'Move dead stock aside', 'OTHER' => 'Other'];

    protected $connection = 'tm2inv';

    protected $table = 'inv_transfer';

    protected $primaryKey = 'id_transfer';

    protected $guarded = ['id_transfer'];

    protected $casts = ['transfer_date' => 'date', 'stat_confirm' => 'boolean', 'stat_cancel' => 'boolean', 'confirmed_at' => 'datetime', 'shipped_at' => 'datetime', 'received_at' => 'datetime'];

    public function lines(): HasMany
    {
        return $this->hasMany(InvTransferLine::class, 'transfer_id', 'id_transfer')->orderBy('line_no');
    }

    // Lintas-DB (tm2inv -> tm2mst): query terpisah lewat relasi Eloquent, bukan join.
    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(WhWarehouse::class, 'from_warehouse_id', 'id_warehouse');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(WhWarehouse::class, 'to_warehouse_id', 'id_warehouse');
    }

    // SUBMITTED / REJECTED hanya muncul bila approval Stock Transfer diaktifkan (Setup > Setting Approval, kode TF).
    public function status(): string
    {
        return match (true) {
            (bool) $this->stat_cancel => 'CANCELLED',
            (bool) $this->stat_confirm => 'CONFIRMED',
            in_array($this->flow_status, ['SUBMITTED', 'REJECTED'], true) => $this->flow_status,
            default => 'DRAFT',
        };
    }

    public function label(): string
    {
        return $this->transfer_no ?: 'Draft #'.$this->id_transfer;
    }

    public static function nextNo(): string
    {
        $prefix = 'TF'.now()->format('y');
        $last = static::where('transfer_no', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('transfer_no')->value('transfer_no');

        return $prefix.str_pad((string) ($last ? ((int) substr($last, 4)) + 1 : 1), 5, '0', STR_PAD_LEFT);
    }
}
