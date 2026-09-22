<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// DUA PERINTAH USER 2026-09-22, dikerjakan sekali jalan:
//
// (A) HAPUS data mesin LEGACY. Isi tm2mst.prod_machine masih tercampur
//     warisan ScriptCase yang MENGGABUNGKAN mesin dengan nama KRU:
//       KOMORI_GL440_FANDIK / _AGUS / _DIMAS / _YANTO
//     Fandik, Agus, Dimas, Yanto adalah NAMA ORANG. Satu mesin fisik (Komori
//     GL440) jadi 5 "mesin" berbeda karena sistem lama mengunci baris per
//     mesin+operator per shift. Ditambah 2 jenis sampah lain:
//       - SUBKON_* / SUBCON_PLONG  -> itu SUBKONTRAKTOR, bukan mesin
//       - LAM_YOUBOND              -> itu MEREK FILM laminasi, bukan mesin
//       - LIPAT / LAMINASI MANUAL  -> pekerjaan tangan, bukan unit mesin
//     Migration 2026_09_21_000007 dulu hanya men-set activate=0. Itu keliru:
//     user memberi daftar aset resmi supaya MENGGANTI data lama, bukan
//     berdampingan dengannya.
//
// (B) SETIAP MESIN WAJIB PUNYA PROSES. CMP-01 (kompresor) & FKL-01 (forklift)
//     sebelumnya saya tandai "bukan mesin proses" -- itu salah menurut user.
//     Keduanya punya fungsi produksi nyata dan harus punya proses sendiri:
//     kompresor menyuplai udara bertekanan (tanpanya die cutter & press
//     berhenti), forklift memindahkan bahan antar-stasiun. Prosesnya dibuat,
//     is_process_machine dikembalikan ke 1, lalu dipetakan.
//
// CARA HAPUS -- kenapa tidak DELETE polos:
// Ke-29 baris legacy SEMUANYA masih dirujuk: 29 emp_work_center (LIVE, plus
// 129 baris emp_shift_roster & 37 emp_employee_work_center_skill di
// belakangnya), 11 prd_wo_operation LIVE pada WO nyata (WO2600228 dst, ada
// yang RUNNING & QC_PASSED), 3 iqp_part_process, 12 prod_part_process.
// DELETE polos akan meng-orphan WO yang sedang jalan dan roster HR.
//
// Jadi referensinya DIPINDAH lebih dulu:
//   - Keluarga KOMORI -> OFF-02 (id 1). PASTI: hanya ada satu Komori di
//     daftar aset resmi, dan "Lithrone 40" = "GL440" mesin yang sama. Nama
//     kru dibuang, identitas mesinnya dipertahankan -- histori WO tetap
//     menunjuk unit fisik yang benar.
//   - Sisanya -> machine_id NULL. SENGAJA tidak ditebak: "PLONG 3" tidak bisa
//     dipastikan DIE-01/02/03/04 yang mana, dan menempelkan histori WO ke
//     unit fisik yang SALAH lebih merusak daripada mengosongkannya.
//     prd_wo_operation.process_name tetap menyimpan proses apa yang dikerjakan,
//     jadi yang hilang cuma "di unit mana", yang memang tidak pernah andal di
//     data lama karena tercampur kru.
//
// emp_work_center TIDAK dihapus (roster & skill HR bergantung padanya), hanya
// machine_id-nya diperbaiki. Penamaan work center juga masih tercampur kru
// (WC-KOMORI_GL440_YANTO) -- itu perlu keputusan user tersendiri, dicatat di
// docs/BACKLOG.md, bukan diputuskan di sini.
return new class extends Migration
{
    // Prefix kode yang dipasang migration 2026_09_21_000007 untuk baris lama.
    private const LEGACY_PREFIX = 'ZZOLD-';

    // Nama legacy yang identitas mesinnya PASTI -> kode mesin resmi.
    private const REMAP = [
        'KOMORI L 40' => 'OFF-02',
        'KOMORI_L40' => 'OFF-02',
        'KOMORI_GL440' => 'OFF-02',
        'KOMORI GL440_FANDIK' => 'OFF-02',
        'KOMORI_GL440_AGUS' => 'OFF-02',
        'KOMORI_GL440_DIMAS' => 'OFF-02',
        'KOMORI_GL440_YANTO' => 'OFF-02',
    ];

    // (B) Proses untuk mesin penunjang: [nama, tahap, uom, jenis mesin]
    private const SUPPORT_PROCESSES = [
        ['SUPLAI UDARA', 'PREPRESS', 'JAM', 'Support Equipment', 'CMP-01'],
        ['HANDLING MATERIAL', 'LOGISTIC', 'JAM', 'Support Equipment', 'FKL-01'],
    ];

    public function up(): void
    {
        $mst = DB::connection('tm2mst');

        $legacy = $mst->table('prod_machine')
            ->where('machine_code', 'like', self::LEGACY_PREFIX.'%')
            ->get(['machine_id', 'machine_code', 'machine_name']);

        if ($legacy->isNotEmpty()) {
            $this->repointReferences($mst, $legacy);
            $this->deleteLegacyRows($mst, $legacy);
        }

        $this->coverSupportMachines($mst);
        $this->coverRemainingMachines($mst);
    }

    private function repointReferences(Connection $mst, $legacy): void
    {
        $codeToId = $mst->table('prod_machine')->where('activate', 1)
            ->pluck('machine_id', 'machine_code');

        foreach ($legacy as $old) {
            $target = null;
            $name = trim((string) $old->machine_name);

            if (isset(self::REMAP[$name])) {
                $target = $codeToId[self::REMAP[$name]] ?? null;
            }

            // tm2mst -- FK fisik, harus dipindah sebelum induknya dihapus.
            $mst->table('emp_work_center')->where('machine_id', $old->machine_id)
                ->update(['machine_id' => $target, 'updated_by' => 'migration:purge_legacy_machine']);
            $mst->table('prod_part_process')->where('machine_id', $old->machine_id)
                ->update(['machine_id' => $target, 'updated_by' => 'migration:purge_legacy_machine']);
            $mst->table('prod_part_process_machine_rate')->where('machine_id', $old->machine_id)
                ->update(['machine_id' => $target, 'updated_by' => 'migration:purge_legacy_machine']);

            // Lintas-DB: FK logis, tidak dijaga MySQL -> wajib diurus di sini.
            $this->safeUpdate('tm2prd', 'prd_wo_operation', $old->machine_id, $target);
            $this->safeUpdate('tm2prd', 'prd_breakdown_event', $old->machine_id, $target);
            $this->safeUpdate('tm2sdi', 'iqp_part_process', $old->machine_id, $target);
        }
    }

    private function safeUpdate(string $conn, string $table, int $oldId, ?int $target): void
    {
        try {
            DB::connection($conn)->table($table)->where('machine_id', $oldId)
                ->update(['machine_id' => $target]);
        } catch (Throwable) {
            // Tabel belum ada di environment ini -- bukan alasan menggagalkan purge.
        }
    }

    private function deleteLegacyRows(Connection $mst, $legacy): void
    {
        $ids = $legacy->pluck('machine_id')->all();

        // prod_process_machine ikut terhapus lewat FK cascade; dihapus eksplisit
        // supaya tidak bergantung pada perilaku cascade.
        $mst->table('prod_process_machine')->whereIn('machine_id', $ids)->delete();
        $mst->table('prod_machine_downtime')->whereIn('machine_id', $ids)->delete();
        $mst->table('prod_machine_changeover')->whereIn('machine_id', $ids)->delete();
        $mst->table('prod_machine')->whereIn('machine_id', $ids)->delete();
    }

    // (B) Kompresor & forklift: buat prosesnya, jadikan mesin proses, petakan.
    private function coverSupportMachines(Connection $mst): void
    {
        $now = now();

        foreach (self::SUPPORT_PROCESSES as [$name, $stage, $uom, $type, $machineCode]) {
            $processId = $mst->table('prod_process')->where('process_name', $name)->value('id_process');

            if (! $processId) {
                $processId = $mst->table('prod_process')->insertGetId([
                    'process_name' => $name,
                    'process_stage' => $stage,
                    'output_uom' => $uom,
                    'inhouse' => true,
                    'is_manual' => false,
                    'activate' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'created_by' => 'migration:purge_legacy_machine',
                    'updated_by' => 'migration:purge_legacy_machine',
                    'data_origin' => 'LIVE',
                    'data_ready' => true,
                ]);
            }

            // Mesin penunjang kini punya proses -> tidak lagi dikecualikan.
            $mst->table('prod_machine')->where('machine_code', $machineCode)->update([
                'is_process_machine' => true,
                'updated_by' => 'migration:purge_legacy_machine',
            ]);

            $machine = $mst->table('prod_machine')->where('machine_code', $machineCode)
                ->first(['machine_id', 'max_speed_per_hour']);

            if ($machine) {
                $this->pair($mst, $processId, $machine->machine_id, $machine->max_speed_per_hour, 1);
            }
        }
    }

    // Jaring pengaman perintah "SETIAP mesin wajib punya proses": mesin aktif
    // yang masih belum punya pasangan dipetakan ke proses yang jenisnya cocok.
    // Kalau tidak ada proses yang cocok, proses dibuat dari nama jenis mesin --
    // lebih baik ada prosesnya daripada mesin menggantung tanpa routing.
    private function coverRemainingMachines(Connection $mst): void
    {
        $byType = [
            'Offset Printing' => 'CETAK',
            'Digital Printing' => 'CETAK',
            'CTP Plate Setter' => 'PLATE MAKING',
            'Paper Cutting' => 'POTONG',
            'Die Cutting' => 'PLONG',
            'Cutting Plotter' => 'PLONG',
            'Folder Gluer' => 'LEM MESIN',
            'Gluing' => 'LEM MESIN',
            'Laminating' => 'LAMINASI',
            'UV Coating' => 'UV',
            'Water-Based Coating' => 'VARNISH',
            'Waxing' => 'WAXING',
            'Pressing' => 'EMBOSS',
            'Shrink Wrapping' => 'WRAPPING',
            'Stripping' => 'KUPAS',
            'Stitching' => 'STITCHING',
            'Seaming' => 'SEAMING',
            'Support Equipment' => 'HANDLING MATERIAL',
        ];

        $uncovered = $mst->table('prod_machine')
            ->where('activate', 1)
            ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('prod_process_machine')
                ->whereColumn('machine_id', 'prod_machine.machine_id')->where('activate', 1))
            ->get(['machine_id', 'machine_code', 'machine_type', 'max_speed_per_hour']);

        foreach ($uncovered as $m) {
            $processName = $byType[$m->machine_type] ?? strtoupper((string) $m->machine_type);
            $processId = $mst->table('prod_process')->where('process_name', $processName)->value('id_process');

            if (! $processId) {
                $processId = $mst->table('prod_process')->insertGetId([
                    'process_name' => $processName,
                    'inhouse' => true,
                    'is_manual' => false,
                    'activate' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'created_by' => 'migration:purge_legacy_machine',
                    'updated_by' => 'migration:purge_legacy_machine',
                    'data_origin' => 'LIVE',
                    'data_ready' => true,
                ]);
            }

            $this->pair($mst, $processId, $m->machine_id, $m->max_speed_per_hour, 2);
        }
    }

    private function pair(Connection $mst, int $processId, int $machineId, $speed, int $rank): void
    {
        $exists = $mst->table('prod_process_machine')
            ->where('process_id', $processId)->where('machine_id', $machineId)->exists();

        if ($exists) {
            $mst->table('prod_process_machine')
                ->where('process_id', $processId)->where('machine_id', $machineId)
                ->update(['activate' => true, 'updated_by' => 'migration:purge_legacy_machine']);

            return;
        }

        $mst->table('prod_process_machine')->insert([
            'process_id' => $processId,
            'machine_id' => $machineId,
            'preference_rank' => $rank,
            'is_certified' => true,
            'speed_per_hour' => $speed ?: null,
            'activate' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'created_by' => 'migration:purge_legacy_machine',
            'updated_by' => 'migration:purge_legacy_machine',
            'data_origin' => 'LIVE',
            'data_ready' => true,
        ]);
    }

    // Baris legacy TIDAK bisa dipulihkan: isinya memang data salah (mesin
    // tercampur kru) yang atas perintah user dibuang. Rollback hanya mencabut
    // proses penunjang yang dibuat di sini.
    public function down(): void
    {
        $mst = DB::connection('tm2mst');
        $ids = $mst->table('prod_process')
            ->whereIn('process_name', array_column(self::SUPPORT_PROCESSES, 0))
            ->where('created_by', 'migration:purge_legacy_machine')
            ->pluck('id_process');

        $mst->table('prod_process_machine')->whereIn('process_id', $ids)->delete();
        $mst->table('prod_process')->whereIn('id_process', $ids)->delete();
    }
};
