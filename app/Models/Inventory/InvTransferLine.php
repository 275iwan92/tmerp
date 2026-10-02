<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Satu baris Stock Transfer = satu item dari satu bin asal ke satu bin tujuan.
class InvTransferLine extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'inv_transfer_line';

    protected $primaryKey = 'id_transfer_line';

    protected $guarded = ['id_transfer_line'];

    protected $casts = ['qty' => 'float', 'unit_cost' => 'float', 'was_dead_stock' => 'boolean'];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(InvTransfer::class, 'transfer_id', 'id_transfer');
    }

    // Kunci posisi stok asal seperti dipakai layar dan WarehouseStock: "RM:123:0:45" = kelas : item : part : bin.
    public function positionKey(): string
    {
        return $this->stock_class.':'.$this->item_id.':'.(int) $this->part_id.':'.$this->from_location_id;
    }
}
