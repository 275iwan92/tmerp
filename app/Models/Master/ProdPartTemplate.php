<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan: master template tarif part (legacy tmhpp.m_part_template). Nilai awal per kolom iqp_costing_part.
 * Sumber hulu: docs/ddl/tmhpp.sql (impor migration 2026_10_16_000500).
 * Input: layar master template (BELUM ada, butir terbuka). Output: nilai tarif untuk CostingAdvisor.
 * Dipakai oleh: App\Services\Costing\CostingAdvisor.
 * Status: BARU 2026-10-16.
 */
class ProdPartTemplate extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_part_template';

    protected $primaryKey = 'id_part_template';

    protected $fillable = ['template_code', 'template_name', 'part_label', 'part_type_code', 'defaults', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['defaults' => 'array', 'activate' => 'boolean'];
}
