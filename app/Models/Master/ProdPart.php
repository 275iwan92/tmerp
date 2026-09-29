<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'parent_part_id',
        'part_kind',
        'join_type_id',
        'qty_in_parent',
        'dieline_spec',
        'flat_w',
        'flat_h',
        'flat_area',
        'source_iqp_part_id',
    ];

    protected $casts = [
        'activate' => 'boolean',
        'color_set_1' => 'array',
        'color_set_2' => 'array',
        'dieline_spec' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProdProduct::class, 'product_id', 'id_product');
    }

    // Rakitan (ASSEMBLY) menggabungkan part anak dengan sambungan mat_join_type; bertingkat tanpa batas.
    public function parentPart(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_part_id', 'id_part');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_part_id', 'id_part');
    }

    public function joinType(): BelongsTo
    {
        return $this->belongsTo(MatJoinType::class, 'join_type_id', 'id_join_type');
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

    public function layoutGroups(): BelongsToMany
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

    // Resolve akun 3-lapis (BACKLOG §12.3 / financeaccounting_delta_partner_category_coa_v2.md
    // §2.4) -- part -> product -> kategori produk (fallback terakhir). Menutup gap
    // "SFG tanpa fallback kategori" di StockPostingService::postUsageJournal() (dulu
    // baca stock_asset_account_id/cogs_account_id langsung, TANPA fallback apa pun).
    public function resolveStockAccountId(): ?int
    {
        return $this->stock_asset_account_id
            ?? $this->product?->stock_asset_account_id
            ?? $this->product?->category?->default_stock_account_id;
    }

    public function resolveCogsAccountId(): ?int
    {
        return $this->cogs_account_id
            ?? $this->product?->cogs_account_id
            ?? $this->product?->category?->default_cogs_account_id;
    }
}
