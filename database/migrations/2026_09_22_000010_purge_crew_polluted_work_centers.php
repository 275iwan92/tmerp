<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Lanjutan purge mesin legacy: pencemaran YANG SAMA (mesin + nama kru) juga
// ada di tm2mst.emp_work_center, karena work center dulu di-generate 1:1 dari
// baris prod_machine warisan ScriptCase.
//
//   WC-KOMORI_GL440_FANDIK / _AGUS / _DIMAS / _YANTO
//     -> empat "work center" untuk SATU mesin fisik (Komori GL440 = OFF-02),
//        bedanya cuma nama operator. Digabung jadi satu.
//   WC-SUBKON_* / WC-SUBCON_PLONG
//     -> subkontraktor, bukan work center internal. Dibuang.
//   WC-LAM_YOUBOND
//     -> merek film laminasi, bukan work center. Dibuang.
//
// Sudah diukur dulu sebelum menghapus (bukan asumsi): 10 work center tercemar,
// ketergantungannya hanya 12 baris emp_employee_work_center_skill dan
// NOL baris emp_shift_roster. Jadi aman dibereskan sekarang.
//
// 12 baris skill itu TIDAK dibuang -- isinya fakta yang masih berguna
// ("pegawai ini tersertifikasi di Komori GL440"), hanya dipindah ke work
// center kanonis. Duplikat (pegawai yang sama tercatat di 2 work center kru
// yang ternyata mesin yang sama) digabung, bukan diinsert ganda.
return new class extends Migration
{
    private const CREW_WORDS = ['FANDIK', 'AGUS', 'DIMAS', 'YANTO'];

    private const NOT_WORK_CENTERS = ['SUBKON', 'SUBCON', 'YOUBOND'];

    public function up(): void
    {
        $mst = DB::connection('tm2mst');

        $this->mergeCrewNamedCenters($mst);
        $this->dropNonWorkCenters($mst);
    }

    // Gabungkan work center yang cuma beda nama kru untuk mesin fisik yang sama.
    private function mergeCrewNamedCenters(Connection $mst): void
    {
        $crewCenters = $mst->table('emp_work_center')
            ->whereNotNull('machine_id')
            ->where(function ($q) {
                foreach (self::CREW_WORDS as $w) {
                    $q->orWhere('work_center_name', 'like', "%$w%");
                }
            })
            ->get(['work_center_id', 'work_center_name', 'machine_id']);

        foreach ($crewCenters->groupBy('machine_id') as $machineId => $group) {
            $canonical = $this->canonicalCenterFor($mst, (int) $machineId, $group);

            if (! $canonical) {
                continue;
            }

            foreach ($group as $wc) {
                if ($wc->work_center_id === $canonical) {
                    continue;
                }

                $this->moveSkills($mst, $wc->work_center_id, $canonical);
                $mst->table('emp_work_center')->where('work_center_id', $wc->work_center_id)->delete();
            }
        }
    }

    // Work center kanonis untuk sebuah mesin: yang namanya TIDAK mengandung
    // nama kru. Kalau semuanya tercemar, ambil id terkecil lalu namanya
    // dibersihkan ikut kode mesin -- jangan biarkan nama kru jadi nama resmi.
    private function canonicalCenterFor(Connection $mst, int $machineId, $group): ?int
    {
        $clean = $mst->table('emp_work_center')
            ->where('machine_id', $machineId)
            ->where(function ($q) {
                foreach (self::CREW_WORDS as $w) {
                    $q->where('work_center_name', 'not like', "%$w%");
                }
            })
            ->orderBy('work_center_id')
            ->first(['work_center_id']);

        if ($clean) {
            return (int) $clean->work_center_id;
        }

        $machine = $mst->table('prod_machine')->where('machine_id', $machineId)
            ->first(['machine_code', 'machine_name']);
        $keep = (int) $group->min('work_center_id');

        if ($machine) {
            $mst->table('emp_work_center')->where('work_center_id', $keep)->update([
                'work_center_code' => 'WC-'.$machine->machine_code,
                'work_center_name' => $machine->machine_name,
                'updated_by' => 'migration:purge_crew_work_centers',
            ]);
        }

        return $keep;
    }

    // Pindahkan sertifikasi pegawai, gabungkan kalau pegawainya sudah tercatat
    // di work center tujuan (UNIQUE pegawai+work center kemungkinan dipasang).
    private function moveSkills(Connection $mst, int $fromWc, int $toWc): void
    {
        $skills = $mst->table('emp_employee_work_center_skill')
            ->where('work_center_id', $fromWc)->get();

        foreach ($skills as $skill) {
            $already = $mst->table('emp_employee_work_center_skill')
                ->where('work_center_id', $toWc)
                ->where('employee_id', $skill->employee_id)
                ->exists();

            $idColumn = property_exists($skill, 'skill_id') ? 'skill_id' : 'id';

            if ($already) {
                $mst->table('emp_employee_work_center_skill')
                    ->where($idColumn, $skill->{$idColumn})->delete();
            } else {
                $mst->table('emp_employee_work_center_skill')
                    ->where($idColumn, $skill->{$idColumn})
                    ->update(['work_center_id' => $toWc]);
            }
        }
    }

    // Subkontraktor & merek bahan bukan work center -> dibuang beserta
    // sisa ketergantungannya (sudah diverifikasi: 0 skill, 0 roster).
    private function dropNonWorkCenters(Connection $mst): void
    {
        $ids = $mst->table('emp_work_center')
            ->where(function ($q) {
                foreach (self::NOT_WORK_CENTERS as $w) {
                    $q->orWhere('work_center_name', 'like', "%$w%")
                        ->orWhere('work_center_code', 'like', "%$w%");
                }
            })
            ->pluck('work_center_id');

        if ($ids->isEmpty()) {
            return;
        }

        $mst->table('emp_employee_work_center_skill')->whereIn('work_center_id', $ids)->delete();
        $mst->table('emp_work_center')->whereIn('work_center_id', $ids)->delete();
    }

    public function down(): void
    {
        // Tidak dipulihkan: isinya data salah (work center = mesin + nama kru,
        // dan subkontraktor yang disamakan dengan work center internal) yang
        // atas perintah user dibuang. Cadangan mentah ada di
        // storage/app/backup_legacy_machine_*.json.
    }
};
