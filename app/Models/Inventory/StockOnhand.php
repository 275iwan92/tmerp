<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// VIEW hanya-baca saldo stok sekarang per item x lokasi: v_rm_onhand / v_fg_onhand / v_sfg_onhand (satu kelas model,
// tabelnya dipilih lewat of()). View tidak punya PK: pemanggil memilih kolom row_id sendiri (selectRaw ... AS row_id).
class StockOnhand extends Model
{
    public const VIEWS = ['RM' => 'v_rm_onhand', 'FG' => 'v_fg_onhand', 'SFG' => 'v_sfg_onhand'];

    // Kolom id item di tiap view.
    public const ITEM_KEYS = ['RM' => 'material_id', 'FG' => 'product_id', 'SFG' => 'parts_id'];

    protected $connection = 'tm2inv';

    protected $table = 'v_rm_onhand';

    protected $primaryKey = 'row_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    public static function of(string $stockClass): Builder
    {
        return (new static)->setTable(self::VIEWS[$stockClass])->newQuery();
    }
}
