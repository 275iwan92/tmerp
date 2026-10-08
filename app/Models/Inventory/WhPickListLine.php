<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Satu baris pick list = satu alokasi dari satu palet (bagian dari kebutuhan satu baris booking), atau satu baris SHORT
// (bagian yang tidak tertutup stok yang boleh dialokasikan). qty_need = kebutuhan total baris booking, diulang di tiap baris bahan itu.
class WhPickListLine extends Model
{
    public const STATUSES = ['ALLOCATED' => 'info', 'PICKED' => 'success', 'SHORT' => 'danger', 'CANCELLED' => 'neutral'];

    protected $connection = 'tm2inv';

    protected $table = 'wh_pick_list_line';

    protected $primaryKey = 'id_pick_list_line';

    protected $guarded = ['id_pick_list_line'];

    protected $casts = ['qty_need' => 'float', 'qty_allocated' => 'float', 'qty_picked' => 'float', 'scan_pallet_at' => 'datetime', 'scan_bin_at' => 'datetime'];

    public function pickList(): BelongsTo
    {
        return $this->belongsTo(WhPickList::class, 'pick_list_id', 'id_pick_list');
    }
}
