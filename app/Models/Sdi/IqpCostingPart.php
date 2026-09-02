<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IqpCostingPart extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_costing_part';

    protected $primaryKey = 'id_costing_part';

    protected $fillable = [
        'costing_id',
        'part_id',
        'no_urut',
        'part_name',
        'part_type_code',
        'qty_per_set',
        'i_dim_panjang_mm',
        'i_dim_lebar_mm',
        'i_dim_tinggi_mm',
        'grammage_id',
        'i_dc_persen_override',
        'i_panjang_plano_cm',
        'i_lebar_plano_cm',
        'i_cut_per_plano',
        'i_isi_per_lb',
        'i_jumlah_warna',
        'i_ongkos_cetak_rim',
        'i_ongkos_plong_lb',
        'i_ongkos_lem_pc',
        'i_film_faktor',
        'i_film_rate',
        'i_pisau_faktor',
        'i_pisau_rate_rp',
        'i_ongkos_plate_warna',
        'i_sf_ef_rate_override',
        'sheet_rate_id',
        'supplier_sheet_code',
        'flute_type',
        'i_lidah_lem_mm',
        'i_isi_per_lb_kerja',
        'i_ongkos_print_pc',
        'i_ongkos_diecut_pc',
        'i_ongkos_slitter_pc',
        'i_ongkos_slotter_pc',
        'i_ongkos_join_pc',
        'i_ongkos_partisi_pc',
        'i_ongkos_layer_pc',
        'i_ongkos_qc_pack_pc',
        'i_ongkos_kirim_pc',
        'c_panjang_sheet_cm',
        'c_lebar_sheet_cm',
        'c_isi_trim',
        'c_lebar_trim_cm',
        'c_running_meter',
        'c_discount_rm',
        'c_sheet_cost_pc',
        'c_lam_flat_cost_pc',
        'c_sf_ef_cost_pc',
        'c_print_cost_pc',
        'c_area_cetak_cm2',
        'c_jumlah_cetak_lc',
        'c_jumlah_bahan_lb',
        'c_paper_cost_pc',
        'c_cetak_cost_pc',
        'c_plong_cost_pc',
        'c_lem_cost_pc',
        'c_total_coating_pc',
        'c_film_cost_order',
        'c_plate_cost_order',
        'c_knife_cost_order',
        'c_tooling_cost_order',
        'c_process_cost_pc',
        'c_total_part_cost_pc',
        'description_calc',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'qty_per_set' => 'decimal:2',
        'i_dim_panjang_mm' => 'decimal:2',
        'i_dim_lebar_mm' => 'decimal:2',
        'i_dim_tinggi_mm' => 'decimal:2',
        'i_dc_persen_override' => 'decimal:4',
        'i_panjang_plano_cm' => 'decimal:4',
        'i_lebar_plano_cm' => 'decimal:4',
        'i_cut_per_plano' => 'decimal:4',
        'i_isi_per_lb' => 'decimal:4',
        'i_ongkos_cetak_rim' => 'decimal:4',
        'i_ongkos_plong_lb' => 'decimal:4',
        'i_ongkos_lem_pc' => 'decimal:4',
        'i_film_faktor' => 'decimal:4',
        'i_film_rate' => 'decimal:4',
        'i_pisau_faktor' => 'decimal:4',
        'i_pisau_rate_rp' => 'decimal:4',
        'i_ongkos_plate_warna' => 'decimal:4',
        'i_sf_ef_rate_override' => 'decimal:4',
        'i_lidah_lem_mm' => 'decimal:2',
        'i_isi_per_lb_kerja' => 'decimal:4',
        'i_ongkos_print_pc' => 'decimal:4',
        'i_ongkos_diecut_pc' => 'decimal:4',
        'i_ongkos_slitter_pc' => 'decimal:4',
        'i_ongkos_slotter_pc' => 'decimal:4',
        'i_ongkos_join_pc' => 'decimal:4',
        'i_ongkos_partisi_pc' => 'decimal:4',
        'i_ongkos_layer_pc' => 'decimal:4',
        'i_ongkos_qc_pack_pc' => 'decimal:4',
        'i_ongkos_kirim_pc' => 'decimal:4',
        'c_panjang_sheet_cm' => 'decimal:4',
        'c_lebar_sheet_cm' => 'decimal:4',
        'c_isi_trim' => 'decimal:4',
        'c_lebar_trim_cm' => 'decimal:4',
        'c_running_meter' => 'decimal:4',
        'c_discount_rm' => 'decimal:4',
        'c_sheet_cost_pc' => 'decimal:4',
        'c_lam_flat_cost_pc' => 'decimal:4',
        'c_sf_ef_cost_pc' => 'decimal:4',
        'c_print_cost_pc' => 'decimal:4',
        'c_area_cetak_cm2' => 'decimal:4',
        'c_jumlah_cetak_lc' => 'decimal:4',
        'c_jumlah_bahan_lb' => 'decimal:4',
        'c_paper_cost_pc' => 'decimal:4',
        'c_cetak_cost_pc' => 'decimal:4',
        'c_plong_cost_pc' => 'decimal:4',
        'c_lem_cost_pc' => 'decimal:4',
        'c_total_coating_pc' => 'decimal:4',
        'c_film_cost_order' => 'decimal:4',
        'c_plate_cost_order' => 'decimal:4',
        'c_knife_cost_order' => 'decimal:4',
        'c_tooling_cost_order' => 'decimal:4',
        'c_process_cost_pc' => 'decimal:4',
        'c_total_part_cost_pc' => 'decimal:4',
    ];

    public function costing(): BelongsTo
    {
        return $this->belongsTo(IqpCosting::class, 'costing_id', 'id_costing');
    }

    public function inquiryPart(): BelongsTo
    {
        return $this->belongsTo(IqpPart::class, 'part_id', 'id_part');
    }

    public function coatings(): HasMany
    {
        return $this->hasMany(IqpCostingPartCoating::class, 'costing_part_id', 'id_costing_part')->orderBy('seq');
    }

    // grammage_id -> mat_grammage (tm2mst) & part_type_code -> prod_part_type (tm2mst):
    // cross-DB, relasi logical (Eloquent query terpisah), bukan Eloquent relation fisik.
}
