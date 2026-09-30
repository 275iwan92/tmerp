<?php

namespace App\Services\Production\SpeedTarget;

use App\Models\Master\ProdMachine;
use App\Models\Master\ProdProcess;
use App\Models\Master\ProdProcessMachine;
use Illuminate\Support\Facades\DB;

// Memuat MachineSpeedContext untuk semua mesin produksi aktif. Satu kali baca per sumber (bukan per mesin): riwayat
// lama ±120 rb baris dibaca sekali lalu dibagi per nama mesin/proses di PHP (tm2old beda DB — pola 1 CLAUDE.md §2).
final class SpeedDataRepository
{
    public function __construct(private readonly SpeedSettings $settings) {}

    /** @return list<MachineSpeedContext> */
    public function contexts(?array $machineIds = null): array
    {
        $machines = ProdMachine::query()->with('machineModel')
            ->where('activate', true)->where('is_process_machine', true)
            ->orderBy('machine_code')->get();

        $processes = ProdProcess::query()->get()->keyBy('id_process');
        $caps = ProdProcessMachine::query()->where('activate', true)
            ->orderBy('preference_rank')->orderBy('process_id')->get()->groupBy('machine_id');

        // Proses yang hanya dikerjakan satu jenis mesin -> riwayat proses lama boleh dipinjam (lihat HistorySource).
        $typesPerProcess = [];
        foreach ($machines as $m) {
            foreach ($caps[$m->machine_id] ?? [] as $cap) {
                $name = mb_strtoupper((string) $processes->get($cap->process_id)?->process_name);
                $typesPerProcess[$name][$m->machine_type] = true;
            }
        }
        $singleTypeProcesses = array_keys(array_filter($typesPerProcess, fn ($types) => count($types) === 1));

        $uomByProcess = $processes->mapWithKeys(fn ($p) => [mb_strtoupper((string) $p->process_name) => $p->output_uom])->all();
        [$legacyByName, $legacyByProcess] = $this->legacySamples($machines->pluck('legacy_machine_names')->filter()->all(), $singleTypeProcesses);
        $v2 = $this->v2Samples($uomByProcess);

        $out = [];
        foreach ($machines as $m) {
            if ($machineIds !== null && ! in_array($m->machine_id, $machineIds, true)) {
                continue;
            }
            $capRows = [];
            foreach ($caps[$m->machine_id] ?? [] as $cap) {
                $p = $processes->get($cap->process_id);
                if ($p) {
                    $capRows[] = [
                        'capability_id' => $cap->capability_id, 'process_id' => $p->id_process, 'process_name' => $p->process_name, 'uom' => $p->output_uom,
                        'current' => $cap->speed_per_hour !== null ? (float) $cap->speed_per_hour : null, 'rank' => (int) $cap->preference_rank,
                    ];
                }
            }
            $primary = $capRows ? $processes->get($capRows[0]['process_id']) : null;
            $model = $m->machineModel;

            $out[] = new MachineSpeedContext(
                machineId: $m->machine_id,
                code: $m->machine_code,
                type: (string) $m->machine_type,
                drive: $model?->drive,
                maxSpeed: ($m->max_speed_per_hour ?: $model?->speed_per_hour) ? (float) ($m->max_speed_per_hour ?: $model->speed_per_hour) : null,
                modelId: $model?->id_machine_model,
                modelCode: $model?->model_code,
                currentTarget: $m->speed_target_per_hour ? (float) $m->speed_target_per_hour : null,
                capabilities: $capRows,
                v2Samples: $v2[$m->machine_id] ?? [],
                legacySamples: $this->matchLegacy($legacyByName, (string) $m->legacy_machine_names),
                legacyProcessSamples: $legacyByProcess,
                processDefaultSpeed: $primary?->default_speed_per_hour ? (float) $primary->default_speed_per_hour : null,
                legacyNames: $m->legacy_machine_names,
            );
        }

        return $out;
    }

    /** @return array<int, list<SpeedSample>> realisasi WO v2 per machine_id */
    private function v2Samples(array $uomByProcess): array
    {
        try {
            $ops = DB::connection('tm2prd')->table('prd_wo_operation')
                ->whereNotNull('machine_id')->whereNotNull('actual_start')->whereNotNull('actual_finish')
                ->whereIn('task_status', ['DONE', 'QC_PASSED'])
                ->where('actual_finish', '>=', now()->subDays((int) $this->settings->num('SPEED_V2_LOOKBACK_DAYS')))
                ->get(['operation_id', 'machine_id', 'process_name', 'qty', 'actual_start', 'actual_finish']);
            $good = DB::connection('tm2prd')->table('prd_wo_operation_output')
                ->whereIn('operation_id', $ops->pluck('operation_id'))
                ->groupBy('operation_id')->selectRaw('operation_id, SUM(qty_good) q')->pluck('q', 'operation_id');
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($ops as $op) {
            $minutes = (strtotime($op->actual_finish) - strtotime($op->actual_start)) / 60;
            $qty = (float) ($good[$op->operation_id] ?? 0) ?: (float) $op->qty;
            if ($minutes > 0) {
                $out[$op->machine_id][] = new SpeedSample($qty, $minutes, $op->process_name, $uomByProcess[mb_strtoupper((string) $op->process_name)] ?? null, substr($op->actual_start, 0, 10));
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $nameLists  isi legacy_machine_names semua mesin
     * @return array{0: array<string, list<SpeedSample>>, 1: array<string, list<SpeedSample>>} per nama mesin lama, per nama proses
     */
    private function legacySamples(array $nameLists, array $singleTypeProcesses): array
    {
        $byName = [];
        $byProcess = [];
        try {
            $rows = DB::connection('tm2old')->table('prd_output')
                ->where('actual_qty_good', '>', 0)->whereColumn('actual_stop_time', '>', 'actual_start_time')
                ->selectRaw('UPPER(TRIM(machine_name)) m, UPPER(TRIM(process_name)) p, uom_code u, actual_qty_good q, TIMESTAMPDIFF(SECOND, actual_start_time, actual_stop_time) / 60 mins, DATE(actual_start_time) d')
                ->cursor();
            foreach ($rows as $r) {
                $s = new SpeedSample((float) $r->q, (float) $r->mins, $r->p, $r->u, $r->d);
                $byName[$r->m][] = $s;
                if (in_array($r->p, $singleTypeProcesses, true)) {
                    $byProcess[$r->p][] = $s;
                }
            }
        } catch (\Throwable) {
            return [[], []];
        }

        return [$byName, $byProcess];
    }

    // "PLONG 1, KOMORI_GL440*" -> catatan milik nama-nama itu; * di akhir = awalan.
    private function matchLegacy(array $byName, string $names): array
    {
        $out = [];
        foreach (array_filter(array_map(fn ($n) => mb_strtoupper(trim($n)), explode(',', $names))) as $pattern) {
            foreach ($byName as $name => $samples) {
                $hit = str_ends_with($pattern, '*') ? str_starts_with((string) $name, rtrim($pattern, '*')) : $name === $pattern;
                if ($hit) {
                    array_push($out, ...$samples);
                }
            }
        }

        return $out;
    }
}
