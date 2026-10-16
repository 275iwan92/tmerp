<?php

/*
 * REMARK
 * Tujuan       : Menentukan apakah spesifikasi sebuah Work Order sudah terkunci produksi: sejak operasi pertama berjalan (bahan utama mulai diproses) tidak ada lagi perubahan bahan atau urutan proses (Jo 2026-10-16).
 * Sumber hulu  : tm2prd.prd_wo_operation (task_status RUNNING atau selesai); sinyal pengeluaran bahan utama menyusul.
 * Input        : id Work Order.
 * Output       : true bila sudah terkunci.
 * Dipakai oleh : SpecChangeService (menolak permintaan substitusi pada WO yang sudah berjalan).
 * Status       : Aktif. Pengecualian tambahan proses sesudah QC jelek menyusul bersama kelas 3.
 */

namespace App\Services\Production\SpecChange;

use App\Support\V2\Data\Ppic;
use Illuminate\Support\Facades\DB;

final class SpecLock
{
    public static function locked(int $woId): bool
    {
        return DB::connection('tm2prd')->table('prd_wo_operation')->where('wo_id', $woId)
            ->whereIn('task_status', array_merge(['RUNNING'], Ppic::DONE))->exists();
    }
}
