<?php

declare(strict_types=1);

namespace App\Services\Pur;

use App\Models\Sys\SysConstant;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;

// Deteksi kurang kirim (Q-13, bagian 7.6): bahan yang dikeluarkan ke WO lebih banyak daripada (output baik + waste STANDAR)
// berarti bahan yang tercatat datang tidak pernah ada seluruhnya.
//   Dugaan kekurangan = bahan keluar bersih ke WO - (output baik + waste standar), dalam satuan bahan.
//   Waste aktual di atas standar adalah varians pemakaian, bukan kurang kirim: karena itu dugaan ini baru jadi kekurangan
//   setelah Kepala Gudang menghitung / menimbang ulang (ShortageRequestService::recount). Tanpa bukti = varians pemakaian.
//   Toleransi mengikuti toleransi PO baris penerimaan (po_line.c_tol_pct, kosong = RECEIVE_TOLERANCE_PCT), dihitung dari qty bahan keluar.
// Yang BELUM otomatis: output baik produksi (prd_wo_operation_output.qty_good) dalam satuan produk tidak punya konversi ke satuan bahan, jadi
// output dalam satuan bahan diketik petugas; bahan keluar bersih dan waste standar WO diisikan dari data yang ada.
final class ShortageDetector
{
    /** @return array{candidate: float, tol_qty: float, over_tolerance: bool} */
    public static function evaluate(float $issuedNet, float $outputGood, float $wasteStandard, float $tolPct): array
    {
        $candidate = max(0.0, round($issuedNet - ($outputGood + $wasteStandard), 4));
        $tolQty = round(max(0.0, $issuedNet) * max(0.0, $tolPct) / 100, 4);

        return ['candidate' => $candidate, 'tol_qty' => $tolQty, 'over_tolerance' => $candidate > 0 && $candidate > $tolQty];
    }

    // Toleransi penerimaan baris PO (persen); tanpa baris PO atau tanpa toleransi sendiri = pengaturan umum RECEIVE_TOLERANCE_PCT.
    public static function tolerancePct(?int $poLineId): float
    {
        $own = $poLineId ? DB::connection('tm2pur')->table('po_line')->where('po_line_id', $poLineId)->value('c_tol_pct') : null;

        return (float) ($own ?? SysConstant::number('RECEIVE_TOLERANCE_PCT', 5));
    }

    /**
     * Bahan keluar bersih satu WO dan satu bahan (v_inv_wo_reconcile) beserta waste standar WO bila ada.
     *
     * @return array{wo_id: int, wo_no: string, material_id: int, material_code: string, material_name: string, uom: string, issued_net: float, waste_standard: float}|null
     */
    public static function reconcile(int $woId, int $materialId): ?array
    {
        $row = DB::connection('tm2inv')->table('v_inv_wo_reconcile')->where('wo_id', $woId)->where('material_id', $materialId)->first();
        if (! $row) {
            return null;
        }
        $waste = DB::connection('tm2prd')->table('prd_wo_material')->where('wo_id', $woId)->where('material_id', $materialId)->sum('cutting_yield_waste_qty');

        return ['wo_id' => $woId, 'wo_no' => (string) $row->wo_no, 'material_id' => $materialId, 'material_code' => (string) $row->material_code, 'material_name' => (string) $row->material_name,
            'uom' => (string) $row->uom_code, 'issued_net' => (float) $row->qty_net_used, 'waste_standard' => (float) $waste];
    }

    /** @return array<string, string> "wo_id:material_id" => label, untuk pilihan di form (WO yang sudah mengeluarkan bahan) */
    public static function reconcileOptions(): array
    {
        return DB::connection('tm2inv')->table('v_inv_wo_reconcile')->where('qty_net_used', '>', 0)->orderByDesc('wo_id')->limit(300)->get()
            ->mapWithKeys(fn ($r) => [$r->wo_id.':'.$r->material_id => $r->wo_no.' · '.$r->material_code.' · '.$r->material_name.' · '.Locale::decimal((float) $r->qty_net_used, 2).' '.$r->uom_code])->all();
    }
}
