<?php

/*
 * REMARK
 * Tujuan       : Bahan cadangan: bahan pengganti yang pernah disetujui untuk bahan asal tertentu, disimpan sebagai saran pada substitusi berikutnya (persetujuan tetap diminta tiap kali).
 * Sumber hulu  : App\Services\Production\SpecChange\SpecChangeService::approveWithOtp (pilihan "simpan sebagai bahan cadangan").
 * Input        : -
 * Output       : material_id (asal), alt_material_id (cadangan), catatan, penyetuju.
 * Dipakai oleh : SpecChangeService; saran pada kartu substitusi Form WO.
 * Status       : Aktif.
 */

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class ProdMaterialAlt extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_material_alt';

    protected $primaryKey = 'id_alt';

    protected $guarded = [];

    protected $casts = ['approved_at' => 'datetime', 'activate' => 'boolean'];
}
