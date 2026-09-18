<?php

namespace App\Services\ProDev;

use App\Models\Sys\SysConstant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Takaran pemakaian bahan & persen waste yang BELAJAR dari data produksi nyata
// (instruksi user 2026-09-18). Engine hanya MENGUSULKAN; officer/admin yang
// memutuskan di layar Setting Aturan BOP -- kecuali aturan itu diberi tanda
// auto_apply. Nilai yang diisi manual officer tidak pernah ditimpa.
//
// Cara hitung (sengaja memakai RASIO, bukan menurunkan ulang geometri):
//   takaran usulan = takaran sekarang x (pemakaian NYATA / pemakaian RENCANA)
// Pemakaian rencana = prd_wo_material.qty_required (hasil BOM saat WO lahir),
// pemakaian nyata = tm2inv.v_inv_wo_reconcile.qty_net_used (keluar gudang -
// kembali). Jadi begitu pencatatan keluar/kembali gudang jalan, takaran ikut
// menyesuaikan sendiri tanpa perlu rumus baru per jenis bahan.
//
// Waste per proses = defect / (good + defect) dari pencatatan output produksi
// (prd_wo_operation_output + _defect).
class UsageRateEngine
{
    public function minSample(): int
    {
        return max(1, (int) SysConstant::number('PRODEV_RATE_MIN_SAMPLE', 5));
    }

    /**
     * @return array{rules: array, applied: int, sample_total: int, from: string, note: ?string}
     */
    public function refresh(Carbon $from, bool $apply = true): array
    {
        $mst = DB::connection('tm2mst');
        $rules = $mst->table('prod_bop_rule as r')
            ->leftJoin('prod_process as p', 'p.id_process', '=', 'r.process_id')
            ->where('r.activate', 1)
            ->get(['r.id_bop_rule', 'r.material_role', 'r.usage_rate', 'r.usage_basis', 'r.waste_pct',
                'r.auto_apply', 'r.rate_source', 'p.process_name']);

        $usage = $this->materialUsage($from);
        $waste = $this->processWaste($from);
        $min = $this->minSample();
        $out = [];
        $applied = 0;

        foreach ($rules as $rule) {
            $rateSuggested = null;
            $wasteSuggested = null;
            $sample = 0;

            if ($rule->material_role && $rule->usage_rate !== null && isset($usage[$rule->material_role])) {
                $u = $usage[$rule->material_role];
                if ($u['planned'] > 0 && $u['actual'] > 0) {
                    $rateSuggested = round((float) $rule->usage_rate * $u['actual'] / $u['planned'], 4);
                    $sample = max($sample, $u['wo']);
                }
            }

            if ($rule->process_name && isset($waste[$rule->process_name])) {
                $w = $waste[$rule->process_name];
                if ($w['base'] > 0) {
                    $wasteSuggested = round($w['defect'] / $w['base'] * 100, 2);
                    $sample = max($sample, $w['wo']);
                }
            }

            if ($rateSuggested === null && $wasteSuggested === null) {
                continue;
            }

            $row = [
                'rate_suggested' => $rateSuggested,
                'waste_suggested' => $wasteSuggested,
                'suggest_sample' => $sample,
                'suggest_at' => now(),
            ];

            // auto_apply: usulan langsung dipakai, TAPI hanya bila sampelnya cukup.
            if ($apply && $rule->auto_apply && $sample >= $min) {
                $row += array_filter([
                    'usage_rate' => $rateSuggested,
                    'waste_pct' => $wasteSuggested,
                ], fn ($v) => $v !== null) + ['rate_source' => 'AUTO', 'updated_by' => 'usage_rate_engine', 'updated_at' => now()];
                $applied++;
            }

            $mst->table('prod_bop_rule')->where('id_bop_rule', $rule->id_bop_rule)->update($row);

            $out[$rule->id_bop_rule] = [
                'role' => $rule->material_role ?: $rule->process_name,
                'rate_now' => $rule->usage_rate,
                'rate_suggested' => $rateSuggested,
                'waste_now' => $rule->waste_pct,
                'waste_suggested' => $wasteSuggested,
                'sample' => $sample,
                'applied' => $apply && $rule->auto_apply && $sample >= $min,
            ];
        }

        return [
            'rules' => $out,
            'applied' => $applied,
            'sample_total' => $this->woCount($from),
            'from' => $from->toDateString(),
            'note' => $out ? null : 'Belum ada data pemakaian nyata (keluar gudang per WO / output produksi) pada periode ini.',
        ];
    }

    /**
     * Pemakaian rencana vs nyata per peran baris BOM.
     *
     * @return array<string, array{planned: float, actual: float, wo: int}>
     */
    private function materialUsage(Carbon $from): array
    {
        $planned = DB::connection('tm2prd')->table('prd_wo_material as m')
            ->join('prd_wo as w', 'w.wo_id', '=', 'm.wo_id')
            ->where('w.wo_status', 'COMPLETED')
            ->where('w.updated_at', '>=', $from)
            ->whereNotNull('m.material_role')
            ->groupBy('m.material_role')
            ->selectRaw('m.material_role role, SUM(m.qty_required) planned, COUNT(DISTINCT w.wo_id) wo, GROUP_CONCAT(DISTINCT w.wo_id) wo_ids')
            ->get();

        if ($planned->isEmpty()) {
            return [];
        }

        $out = [];

        foreach ($planned as $p) {
            $woIds = array_filter(explode(',', (string) $p->wo_ids));

            // Pemakaian nyata per WO+bahan dari rekonsiliasi gudang (lintas DB,
            // query terpisah -- bukan join Eloquent lintas koneksi).
            $actual = (float) DB::connection('tm2inv')->table('v_inv_wo_reconcile as v')
                ->whereIn('v.wo_id', $woIds)
                ->whereIn('v.material_id', DB::connection('tm2prd')->table('prd_wo_material')
                    ->whereIn('wo_id', $woIds)->where('material_role', $p->role)
                    ->whereNotNull('material_id')->pluck('material_id')->all() ?: [0])
                ->sum('v.qty_net_used');

            $out[$p->role] = ['planned' => (float) $p->planned, 'actual' => $actual, 'wo' => (int) $p->wo];
        }

        return $out;
    }

    /**
     * Waste nyata per nama proses dari pencatatan output produksi.
     *
     * @return array<string, array{defect: float, base: float, wo: int}>
     */
    private function processWaste(Carbon $from): array
    {
        $rows = DB::connection('tm2prd')->table('prd_wo_operation as o')
            ->join('prd_wo as w', 'w.wo_id', '=', 'o.wo_id')
            ->leftJoin('prd_wo_operation_output as out', 'out.operation_id', '=', 'o.operation_id')
            ->leftJoin('prd_wo_operation_output_defect as d', 'd.output_id', '=', 'out.output_id')
            ->where('w.updated_at', '>=', $from)
            ->groupBy('o.process_name')
            ->selectRaw('o.process_name, COALESCE(SUM(out.qty_good),0) good, COALESCE(SUM(d.qty),0) defect, COUNT(DISTINCT w.wo_id) wo')
            ->get();

        $out = [];

        foreach ($rows as $r) {
            $base = (float) $r->good + (float) $r->defect;
            if ($base <= 0) {
                continue;
            }

            // Nama langkah di WO bisa berupa label ("Cetak di mesin: KOMORI GL 440"),
            // jadi dicocokkan ke nama proses master lewat kata kuncinya.
            $out[$this->matchProcess($r->process_name)] = ['defect' => (float) $r->defect, 'base' => $base, 'wo' => (int) $r->wo];
        }

        return $out;
    }

    private function matchProcess(string $label): string
    {
        static $names = null;
        $names ??= DB::connection('tm2mst')->table('prod_process')->pluck('process_name')->all();

        foreach ($names as $n) {
            if (stripos($label, $n) !== false) {
                return $n;
            }
        }

        return $label;
    }

    private function woCount(Carbon $from): int
    {
        return DB::connection('tm2prd')->table('prd_wo')
            ->where('wo_status', 'COMPLETED')->where('updated_at', '>=', $from)->count();
    }
}
