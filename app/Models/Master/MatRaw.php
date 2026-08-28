<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

// Sebelum 2026-08-27 ada 2 tabel raw-material paralel di tm2mst: mat_raw
// (prefix benar, tapi SELALU kosong -- skema lama tak pernah dituntaskan)
// dan m_raw_material (prefix salah, tapi 5894 baris nyata + dipakai Item
// Master/Procurement/Confirm workflow). Digabung: m_raw_material di-RENAME
// jadi mat_raw (kolom TIDAK berubah), model ini (dulu MRawMaterial) yang
// jadi satu-satunya sumber. Lihat migration 2026_08_27_000010.
class MatRaw extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'mat_raw';

    protected $primaryKey = 'material_id';

    const CREATED_AT = 'create_log';

    const UPDATED_AT = 'update_log';

    protected $fillable = [
        'material_code', 'material_name', 'item_type', 'hs_code',
        'category1', 'category2', 'category3', 'category4', 'category5',
        'description', 'brand', 'color', 'gram_weight', 'cm_length', 'cm_width', 'mm_thickness', 'specification',
        'qc_required', 'shelf_life_days', 'moisture_pct',
        'uom_stock', 'uom_purchase', 'purchasable', 'uom_usage',
        'conversion_to_stock', 'conversion_to_usage',
        'suggest_supplier_id', 'tol_purchase', 'lead_time_days', 'purchase_tax_code', 'free_note',
        'min_stock', 'safety_stock', 'reorder_point', 'max_stock', 'cur_stock', 'activate',
        'avg_unitprice', 'last_unitprice', 'def_location_id',
        'costing_method', 'is_fx_sensitive', 'prefix5_id',
        'valuation_class', 'stock_asset_account_id', 'cogs_account_id', 'gr_ir_account_id',
        'cost_center_id', 'profit_center_id',
        'old_item_code', 'old_item_name',
        'stat_confirm', 'confirmed_at', 'confirmed_by',
        'create_by', 'update_by',
    ];

    protected $casts = [
        'purchasable' => 'boolean',
        'activate' => 'boolean',
        'qc_required' => 'boolean',
        'is_fx_sensitive' => 'boolean',
        'stat_confirm' => 'boolean',
        'confirmed_at' => 'datetime',
    ];
}
