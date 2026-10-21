<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan: satu baris per isian ProCost yang disarankan engine: nilai saran, sumber, apakah officer mengubah / sengaja 0,
 *         dan nilai akhir saat Hitung (bahan belajar statistik/ML).
 * Sumber hulu: CostingAdvisor (Inquiry, ProDev, template, konstanta, master, riwayat).
 * Input: CostingAdvisor::prefill / markTouched / learn. Output: penanda sumber di layar dan Laporan Hitung.
 * Dipakai oleh: App\Services\Costing\CostingAdvisor, CostingEntryService, layar v2 procost.
 * Status: BARU 2026-10-16.
 */
class IqpCostingRecommendation extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_costing_recommendation';

    protected $primaryKey = 'id_costing_recommendation';

    protected $guarded = [];

    protected $casts = [
        'recommended_value' => 'float', 'final_value' => 'float', 'qty_pcs' => 'float',
        'is_override' => 'boolean', 'zero_intent' => 'boolean', 'learned_at' => 'datetime',
    ];
}
