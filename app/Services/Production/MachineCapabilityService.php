<?php

namespace App\Services\Production;

use App\Models\Master\ProdMachine;
use App\Models\Master\ProdProcess;
use App\Models\Master\ProdProcessMachine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Otak matriks kapabilitas Proses x Mesin. Semua pemakai (picker mesin di
// routing/BOP, Scheduling, ProCost) WAJIB lewat sini, bukan query tabel
// sendiri-sendiri -- kalau tidak, urutan resolusi parameter akan beda-beda dan
// HPP/jadwal ikut beda untuk data yang sama.
class MachineCapabilityService
{
    // Mesin yang bisa mengerjakan satu proses, sudah diurutkan (mesin utama
    // dulu) dan diberi alasan kalau TIDAK memenuhi syarat. Mesin yang gagal
    // syarat tetap dikembalikan dengan eligible=false + reasons -- pola
    // "engine suggests, officer confirms" (CLAUDE.md §4): officer boleh
    // memilih mesin yang ditandai, tapi harus tahu risikonya.
    //
    // $constraints: sheet_length_mm, sheet_width_mm, gsm, qty
    public function machinesForProcess(int $processId, array $constraints = []): Collection
    {
        $process = ProdProcess::find($processId);

        $caps = ProdProcessMachine::query()
            ->with('machine.department')
            ->where('process_id', $processId)
            ->where('activate', true)
            ->orderBy('preference_rank')
            ->get()
            ->filter(fn ($c) => $c->machine !== null);

        return $caps
            ->map(function (ProdProcessMachine $cap) use ($process, $constraints) {
                $reasons = $this->blockingReasons($cap, $constraints);

                return [
                    'capability' => $cap,
                    'machine' => $cap->machine,
                    'params' => $this->resolveParams($cap, $process),
                    'eligible' => $reasons === [],
                    'reasons' => $reasons,
                    'is_primary' => $cap->preference_rank === 1,
                ];
            })
            ->sortBy([
                // Yang memenuhi syarat selalu di atas, baru urut preferensi.
                fn ($a, $b) => ($b['eligible'] <=> $a['eligible']),
                fn ($a, $b) => ($a['capability']->preference_rank <=> $b['capability']->preference_rank),
                fn ($a, $b) => strcmp($a['machine']->machine_code, $b['machine']->machine_code),
            ])
            ->values();
    }

    // Kebalikannya: proses apa saja yang bisa jalan di satu mesin.
    public function processesForMachine(int $machineId): Collection
    {
        return ProdProcessMachine::query()
            ->with('process.department')
            ->where('machine_id', $machineId)
            ->where('activate', true)
            ->orderBy('preference_rank')
            ->get()
            ->filter(fn ($c) => $c->process !== null)
            ->sortBy([
                fn ($a, $b) => ($a->preference_rank <=> $b->preference_rank),
                fn ($a, $b) => strcmp((string) $a->process->process_name, (string) $b->process->process_name),
            ])
            ->values();
    }

    // RESOLUSI 3 LAPIS -- satu-satunya tempat urutan ini ditulis:
    //   1. baris pasangan proses x mesin (paling spesifik)
    //   2. default proses
    //   3. angka mesin (khusus kecepatan & tarif jam, yang memang milik mesin)
    // Tiap nilai dikembalikan bersama asalnya supaya layar bisa menunjukkan
    // "ini override atau warisan" -- tanpa itu officer tidak tahu angka mana
    // yang perlu dia isi.
    public function resolveParams(ProdProcessMachine $cap, ?ProdProcess $process = null): array
    {
        $process ??= $cap->process;
        $machine = $cap->machine;

        $pick = function (mixed $own, mixed $fromProcess, mixed $fromMachine = null) {
            if ($own !== null && $own !== '') {
                return ['value' => $own, 'source' => 'CAPABILITY'];
            }
            if ($fromProcess !== null && $fromProcess !== '') {
                return ['value' => $fromProcess, 'source' => 'PROCESS'];
            }
            if ($fromMachine !== null && $fromMachine !== '') {
                return ['value' => $fromMachine, 'source' => 'MACHINE'];
            }

            return ['value' => null, 'source' => 'MISSING'];
        };

        return [
            'setup_minutes' => $pick($cap->setup_minutes, $process?->default_setup_minutes),
            'speed_per_hour' => $pick($cap->speed_per_hour, $process?->default_speed_per_hour, $machine?->speed_target_per_hour),
            'waste_pct' => $pick($cap->waste_pct, $process?->default_waste_pct),
            'waste_fixed_qty' => $pick($cap->waste_fixed_qty, $process?->default_waste_fixed_qty),
            'hourly_rate' => $pick($cap->hourly_rate, null, $machine?->hourly_rate),
            'manpower' => $pick($cap->manpower, $process?->default_manpower, $machine?->operator_min),
        ];
    }

    // Alasan sebuah pasangan tidak layak dipakai untuk pekerjaan ini.
    private function blockingReasons(ProdProcessMachine $cap, array $constraints): array
    {
        $m = $cap->machine;
        $reasons = [];

        if (! $m->activate) {
            $reasons[] = 'Mesin nonaktif di master';
        } elseif (in_array($m->machine_status, ProdMachine::STATUSES_BLOCKING, true)) {
            $reasons[] = 'Status mesin: '.$m->statusLabel();
        }

        if (! $m->is_process_machine) {
            $reasons[] = 'Bukan mesin proses (alat penunjang)';
        }

        // Envelope pasangan mengalahkan envelope mesin bila diisi.
        $maxLen = $cap->max_sheet_length_mm ?: $m->max_sheet_length_mm;
        $maxWid = $cap->max_sheet_width_mm ?: $m->max_sheet_width_mm;
        $minLen = $cap->min_sheet_length_mm ?: $m->min_sheet_length_mm;
        $minWid = $cap->min_sheet_width_mm ?: $m->min_sheet_width_mm;

        $len = $constraints['sheet_length_mm'] ?? null;
        $wid = $constraints['sheet_width_mm'] ?? null;

        if ($len && $wid) {
            // Lembar boleh diputar 90 derajat, jadi cukup salah satu orientasi muat.
            $fits = fn ($l, $w) => (! $maxLen || $l <= $maxLen) && (! $maxWid || $w <= $maxWid);

            if ($maxLen && $maxWid && ! $fits($len, $wid) && ! $fits($wid, $len)) {
                $reasons[] = sprintf('Lembar %dx%d mm melebihi batas mesin %dx%d mm', $len, $wid, $maxLen, $maxWid);
            }

            if ($minLen && $minWid && (min($len, $wid) < min($minLen, $minWid))) {
                $reasons[] = sprintf('Lembar lebih kecil dari minimum mesin %dx%d mm', $minLen, $minWid);
            }
        }

        $gsm = $constraints['gsm'] ?? null;
        $gsmMin = $cap->gsm_min ?: $m->gsm_min;
        $gsmMax = $cap->gsm_max ?: $m->gsm_max;

        if ($gsm && $gsmMin && $gsm < $gsmMin) {
            $reasons[] = "Gramatur {$gsm} di bawah minimum {$gsmMin} gsm";
        }
        if ($gsm && $gsmMax && $gsm > $gsmMax) {
            $reasons[] = "Gramatur {$gsm} di atas maksimum {$gsmMax} gsm";
        }

        return $reasons;
    }

    // Perkiraan jam kerja satu batch di pasangan ini: setup + (qty+waste)/speed.
    // NULL bila kecepatan belum terisi di lapis mana pun -- JANGAN diganti 0,
    // pemanggilnya harus tahu angkanya belum ada.
    public function estimateHours(ProdProcessMachine $cap, int $qty, ?ProdProcess $process = null): ?float
    {
        $params = $this->resolveParams($cap, $process);
        $speed = (float) ($params['speed_per_hour']['value'] ?? 0);

        if ($speed <= 0) {
            return null;
        }

        $wastePct = (float) ($params['waste_pct']['value'] ?? 0);
        $wasteFixed = (int) ($params['waste_fixed_qty']['value'] ?? 0);
        $setupHours = ((float) ($params['setup_minutes']['value'] ?? 0)) / 60;

        $grossQty = $qty * (1 + $wastePct / 100) + $wasteFixed;

        return round($setupHours + $grossQty / $speed, 2);
    }

    // Dampak sebelum menonaktifkan proses/mesin. Master ini jadi FK LOGIS
    // lintas-DB (tm2sdi.iqp_part_process, tm2prd.prd_wo_operation) sehingga DB
    // tidak bisa menjaganya -- pengecekan harus di sini (CLAUDE.md §2
    // "Integritas lintas-modul = tanggung jawab Laravel").
    public function whereUsedProcess(int $processId): array
    {
        $name = ProdProcess::find($processId)?->process_name;

        return array_filter([
            'Kapabilitas mesin' => DB::connection('tm2mst')->table('prod_process_machine')
                ->where('process_id', $processId)->count(),
            'Routing Master Produk (prod_part_process)' => DB::connection('tm2mst')->table('prod_part_process')
                ->where('process_id', $processId)->count(),
            'Target rate per produk' => DB::connection('tm2mst')->table('prod_part_process_machine_rate')
                ->where('process_id', $processId)->count(),
            'BOP Inquiry (iqp_part_process)' => $this->safeCount('tm2sdi', 'iqp_part_process', fn ($q) => $q->where('process_id', $processId)),
            'Operasi Work Order' => $name ? $this->safeCount('tm2prd', 'prd_wo_operation', fn ($q) => $q->where('process_name', $name)) : 0,
        ]);
    }

    public function whereUsedMachine(int $machineId): array
    {
        return array_filter([
            'Kapabilitas proses' => DB::connection('tm2mst')->table('prod_process_machine')
                ->where('machine_id', $machineId)->count(),
            'Work Center HR' => DB::connection('tm2mst')->table('emp_work_center')
                ->where('machine_id', $machineId)->count(),
            'Routing Master Produk (prod_part_process)' => DB::connection('tm2mst')->table('prod_part_process')
                ->where('machine_id', $machineId)->count(),
            'Target rate per produk' => DB::connection('tm2mst')->table('prod_part_process_machine_rate')
                ->where('machine_id', $machineId)->count(),
            'Operasi Work Order' => $this->safeCount('tm2prd', 'prd_wo_operation', fn ($q) => $q->where('machine_id', $machineId)),
        ]);
    }

    // Tabel lintas-DB bisa belum ada di environment tertentu (mis. dev yang
    // baru migrate sebagian); jangan sampai audit ikut gagal.
    private function safeCount(string $connection, string $table, callable $where): int
    {
        try {
            $q = DB::connection($connection)->table($table);
            $where($q);

            return $q->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    // Ringkasan audit untuk kotak statistik & panel temuan.
    public function coverageAudit(): array
    {
        $db = DB::connection('tm2mst');

        $processNoMachine = $db->table('prod_process')
            ->where('activate', true)->where('is_manual', false)
            ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('prod_process_machine')
                ->whereColumn('process_id', 'prod_process.id_process')->where('activate', true))
            ->pluck('process_name', 'id_process');

        $machineNoProcess = $db->table('prod_machine')
            ->where('activate', true)->where('is_process_machine', true)
            ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('prod_process_machine')
                ->whereColumn('machine_id', 'prod_machine.machine_id')->where('activate', true))
            ->pluck('machine_code', 'machine_id');

        return [
            'pairs' => $db->table('prod_process_machine')->where('activate', true)->count(),
            'process_without_machine' => $processNoMachine,
            'machine_without_process' => $machineNoProcess,
            'machine_without_size' => $db->table('prod_machine')
                ->where('activate', true)->where('is_process_machine', true)
                ->where(fn ($q) => $q->whereNull('max_sheet_length_mm')->orWhere('max_sheet_length_mm', 0))
                ->pluck('machine_code', 'machine_id'),
            'machine_without_rate' => $db->table('prod_machine')
                ->where('activate', true)->where('is_process_machine', true)
                ->where(fn ($q) => $q->whereNull('hourly_rate')->orWhere('hourly_rate', 0))
                ->pluck('machine_code', 'machine_id'),
        ];
    }
}
