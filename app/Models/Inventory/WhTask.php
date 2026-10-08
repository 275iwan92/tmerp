<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Tugas gudang (v2.01): satu baris = satu pekerjaan yang dikonfirmasi petugas dengan pindai. Dibuat dan diselesaikan hanya lewat
// App\Services\Inventory\WarehouseTaskService; layar tidak menulis tabel ini langsung. Putaway memindahkan stok lewat dokumen
// Stock Transfer (transfer_id), bukan mengubah saldo.
class WhTask extends Model
{
    public const TYPES = ['RECEIVE' => 'Receive', 'PUTAWAY' => 'Putaway', 'PICK' => 'Pick', 'MOVE' => 'Move', 'COUNT' => 'Count'];

    public const STATUSES = ['OPEN' => 'Open', 'DONE' => 'Done', 'CANCELLED' => 'Cancelled'];

    protected $connection = 'tm2inv';

    protected $table = 'wh_task';

    protected $primaryKey = 'id_task';

    protected $guarded = ['id_task'];

    protected $casts = ['qty' => 'float', 'is_urgent' => 'boolean', 'confirmed_at' => 'datetime'];

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(InvPallet::class, 'pallet_id', 'id_pallet');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InvBatch::class, 'batch_id', 'id_batch');
    }

    public function isOpen(): bool
    {
        return $this->status === 'OPEN';
    }

    // Nomor tugas: WT + 2 digit tahun + 5 digit urut (WT2600001).
    public static function nextNo(): string
    {
        $prefix = 'WT'.now()->format('y');
        $last = static::query()->where('task_no', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('task_no')->value('task_no');

        return $prefix.str_pad((string) (($last ? (int) substr($last, strlen($prefix)) : 0) + 1), 5, '0', STR_PAD_LEFT);
    }
}
