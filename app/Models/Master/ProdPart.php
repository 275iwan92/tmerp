<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProdPart extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_part';

    protected $primaryKey = 'id_part';

    protected $fillable = [
        'product_id',
        'part_code',
        'part_name',
        'part_type',
        'qty_per_sku',
        'description',
        'uom_stock',
        'cfg_color',
        'cfg_ink',
        'min_stock',
        'max_stock',
        'cur_stock',
        'activate',
        'stock_asset_account_id',
        'cogs_account_id',
        'cost_center_id',
        'profit_center_id',
        'created_by',
        'updated_by',
        // Kolom teknis ProDev existing (docs/inquiry_product_costing/productdevelopment_tier1.md
        // §4.2 -- di-cek dulu ke DDL sebelum tambah kolom baru, ini SUDAH ADA sejak sebelumnya
        // tapi belum pernah masuk $fillable, jadi layar lama tak pernah bisa mengisinya):
        'waste_default',
        'spoilage_default',
        'lc_per_plano',
        'image_per_lc',
        'printsize_p',
        'printsize_l',
        'dieline_ref',
        'joint_type',
        'ecma_code',
        'cm_length',
        'cm_width',
        'cm_height',
        'finishing',
        'up_per_print',
        'pack_qty',
        // Kolom teknis ProDev BARU (migration 2026_09_02_000021):
        'mastercard_code',
        'die_code',
        'die_w_cm',
        'die_h_cm',
        'sheet_raw_w_cm',
        'sheet_raw_h_cm',
        'color_set_1',
        'color_set_2',
        'flap_side',
        'flap_length_mm',
        'flap_min_mm',
        'flap_max_mm',
        'flap_width_mm',
        'flap_offset_mm',
    ];

    protected $casts = [
        'activate' => 'boolean',
        'color_set_1' => 'array',
        'color_set_2' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProdProduct::class, 'product_id', 'id_product');
    }

    public function processes(): HasMany
    {
        return $this->hasMany(ProdPartProcess::class, 'part_id', 'id_part')->orderBy('step_seq');
    }

    public function finishings(): HasMany
    {
        return $this->hasMany(ProdPartFinishing::class, 'part_id', 'id_part')->orderBy('seq');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProdPartAttachment::class, 'part_id', 'id_part');
    }

    public function layoutGroups(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ProdLayoutGroup::class, 'prod_layout_group_part', 'part_id', 'id_layout_group')
            ->withPivot('qty_up_in_group');
    }

    // Kode Item Master Card sementara (tier1 §7 poin 3) -- pola mnemonic final blm
    // diputuskan, tampilkan mastercard_code kalau sudah diisi manual, kalau belum
    // fallback nomor urut apa adanya.
    public function displayMastercardCode(): string
    {
        return $this->mastercard_code ?: "MC-{$this->id_part}";
    }
}
