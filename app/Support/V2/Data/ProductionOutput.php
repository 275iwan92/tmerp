<?php

/*
 * REMARK
 * Tujuan       : Data layar Production Output (entry hasil produksi di mesin): proses + mesin crew, Master Card job aktif, target, dua timer, LIST OF JOBS.
 * Sumber hulu  : Login -> sec_user.employee_id -> prd_crew_assignment -> operasi (mesin + proses); antrean = operasi CONFIRMED PPIC Planning (Ppic::operations);
 *                Master Card = prod_master_card + prod_master_card_file (storage/app/public/master-card/{id}); timer dari OperatorTimerService.
 * Input        : Pengguna login, ?op= (job aktif), ?machine= & ?process= (hanya administrator / pilihan di antara crew sendiri).
 * Output       : Array untuk objek x-mp::production.output-desk, ['picker' => pilihan "Masuk sebagai"] (administrator tanpa crew), atau ['empty' => alasan].
 * Dipakai oleh : resources/views/v2/screens/production-operator.blade.php.
 * Status       : Aktif di mockup (ddt*). Penugasan crew ke operasi belum punya layar di PPIC Planning (hanya baris prd_crew_assignment yang ada).
 */

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Services\Scheduling\Operator\OperatorTimerService;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class ProductionOutput
{
    /** Done jobs kept at the bottom of LIST OF JOBS (most recent only), so the list stays short. */
    private const DONE_SHOWN = 5;

    /**
     * @param  array{op?: ?int, machine?: ?int, process?: ?string}  $req
     * @return array<string, mixed>
     */
    public static function desk(?object $user, array $req = []): array
    {
        $confirmed = collect(Ppic::operations())->filter(fn ($o) => $o['plan_status'] === 'CONFIRMED' && $o['machine_id'])->values();
        $pairs = self::crewPairs($user->employee_id ?? null);

        // Crew -> machine + process, straight from the login. An administrator without a crew first chooses "Enter as" (process + machine);
        // ?machine=&process= picks among the crew's own pairs (or any confirmed pair for an administrator).
        $admin = (bool) ($user->is_admin ?? false);
        $mine = $admin && $pairs === [] ? $confirmed->map(fn ($o) => [(int) $o['machine_id'], strtoupper($o['process'])])->unique(fn ($p) => implode('|', $p))->values()->all() : $pairs;
        if ($mine === []) {
            return ['empty' => 'No job of your crew is confirmed yet. Jobs appear here when PPIC Planning applies a plan to the live schedule for your machine.'];
        }
        $pick = isset($req['machine'], $req['process']) ? [(int) $req['machine'], strtoupper((string) $req['process'])] : null;
        if ($admin && $pairs === [] && ! ($pick && in_array($pick, $mine, true))) {
            $machines = $confirmed->pluck('machine', 'machine_id');

            return ['picker' => array_map(fn ($p) => [
                'label' => $p[1].' · '.($machines[$p[0]] ?? '#'.$p[0]),
                'url' => route('v2.screen', 'production-operator').'?'.http_build_query(['machine' => $p[0], 'process' => $p[1]]),
            ], $mine)];
        }
        $pair = $pick && in_array($pick, $mine, true) ? $pick : self::frontPair($confirmed, $mine);
        [$machineId, $processKey] = $pair;

        $queue = $confirmed->filter(fn ($o) => (int) $o['machine_id'] === $machineId && strtoupper($o['process']) === $processKey)->values();
        if ($queue->isEmpty()) {
            return ['empty' => 'No confirmed job on this machine and process yet.'];
        }

        $svc = app(OperatorTimerService::class);
        $timers = $svc->timersFor($queue->pluck('op_id')->all());
        $status = $queue->mapWithKeys(fn ($o) => [$o['op_id'] => OperatorTimerService::statusKey($timers[$o['op_id']])]);
        $ordered = $queue->sortBy(fn ($o) => [$status[$o['op_id']] === 'done', $o['start']?->timestamp ?? PHP_INT_MAX])->values();
        $active = $ordered->firstWhere('op_id', (int) ($req['op'] ?? 0))
            ?? $ordered->first(fn ($o) => in_array($status[$o['op_id']], ['setup', 'prod'], true))
            ?? $ordered->first(fn ($o) => $status[$o['op_id']] !== 'done') ?? $ordered->first();

        $doneIds = $ordered->filter(fn ($o) => $status[$o['op_id']] === 'done')->take(self::DONE_SHOWN)->pluck('op_id')->all();
        $list = $ordered->filter(fn ($o) => $status[$o['op_id']] !== 'done' || in_array($o['op_id'], $doneIds, true))->values();
        $base = route('v2.screen', 'production-operator').'?'.http_build_query(['machine' => $machineId, 'process' => $processKey]);
        $woQty = DB::connection('tm2prd')->table('prd_wo')->where('wo_id', $active['wo_id'])->value('qty');
        $perHour = $active['target'] ? (float) $active['target'] : null;
        $targetQty = (float) $active['qty'];

        return [
            'title' => 'PRODUCTION PROCESS : '.$active['process'],
            'machine' => (string) $active['machine'],
            'op' => $active['op_id'],
            'card' => self::card($active, (int) $woQty),
            // TARGET QTY comes from the ProDev engine copied to the Work Order (Qty Order + waste / spill / setup), so it may differ from Qty Order.
            'target' => ['per_hour' => $perHour, 'qty' => $targetQty, 'uom' => (string) $active['uom'],
                'duration_min' => $perHour ? (int) round($targetQty / $perHour * 60) : null],
            'timers' => $timers[$active['op_id']],
            'saved' => $svc->results($active['op_id']),
            'jobs' => $list->map(fn ($o, $i) => [
                'no' => $i + 1, 'wo' => $o['wo_no'], 'product' => self::productLabel($o), 'customer' => (string) ($o['customer'] ?: '—'),
                'qty' => Locale::number($o['qty'], 0).' '.$o['uom'], 'status' => $status[$o['op_id']], 'current' => $o['op_id'] === $active['op_id'],
                'url' => $base.'&op='.$o['op_id'],
            ])->all(),
            'urls' => ['timer' => route('v2.ppic.output.timer', $active['op_id']), 'result' => route('v2.ppic.output.result', $active['op_id'])],
            'reasons' => $svc->rejectReasons(),
        ];
    }

    /** Machine + process pairs of a crew member (by name in prd_crew_assignment, or by the crew chosen in the BOP). @return list<array{int, string}> */
    private static function crewPairs(?int $employeeId): array
    {
        if (! $employeeId) {
            return [];
        }
        // Where the employee was assigned by name (prd_crew_assignment) plus where the crew (work center) of the employee was chosen in the BOP.
        $centers = DB::connection('tm2mst')->table('emp_employee_work_center_skill')->where('employee_id', $employeeId)->where('activate', 1)->pluck('work_center_id');
        $rows = DB::connection('tm2prd')->table('prd_wo_operation as o')->whereNotNull('o.machine_id')
            ->where(fn ($q) => $q->whereIn('o.operation_id', DB::connection('tm2prd')->table('prd_crew_assignment')->where('employee_id', $employeeId)->select('operation_id'))
                ->orWhereIn('o.crew_work_center_id', $centers))
            ->get(['o.machine_id', 'o.process_name']);

        return $rows->map(fn ($r) => [(int) $r->machine_id, strtoupper((string) $r->process_name)])->unique(fn ($p) => implode('|', $p))->values()->all();
    }

    /** The pair that has a running job, else the one with the earliest planned start. @param list<array{int, string}> $pairs */
    private static function frontPair($confirmed, array $pairs): array
    {
        $inPairs = $confirmed->filter(fn ($o) => in_array([(int) $o['machine_id'], strtoupper($o['process'])], $pairs, true));
        $front = $inPairs->first(fn ($o) => $o['task_status'] === 'RUNNING')
            ?? $inPairs->sortBy(fn ($o) => $o['start']?->timestamp ?? PHP_INT_MAX)->first();

        return $front ? [(int) $front['machine_id'], strtoupper($front['process'])] : $pairs[0];
    }

    /** @param array<string, mixed> $o */
    private static function productLabel(array $o): string
    {
        return trim((string) $o['product'].($o['part'] ? ' · '.$o['part'] : ''));
    }

    /** Master Card of the job: dieline + artwork picture, and the numbers (P x L x T and flat size, mm). @param array<string, mixed> $o */
    private static function card(array $o, int $woQty): array
    {
        $m = DB::connection('tm2mst');
        $productId = (int) DB::connection('tm2prd')->table('prd_wo')->where('wo_id', $o['wo_id'])->value('product_id');
        $card = $productId ? $m->table('prod_master_card')->where('product_id', $productId)->orderByDesc('id_master_card')->first() : null;
        $label = preg_match('/PART\s+([A-Z])$/i', (string) $o['part'], $x) ? strtoupper($x[1]) : null;
        $files = $card ? $m->table('prod_master_card_file')->where('master_card_id', $card->id_master_card)->where('file_kind', 'ARTWORK')->orderBy('seq')->get() : collect();
        $file = $files->firstWhere('part_label', $label) ?? $files->firstWhere('part_label', null) ?? $files->first();
        $url = $file && Storage::disk('public')->exists($file->path) ? asset('storage/'.$file->path) : null;
        $mm = fn ($v) => $v === null ? null : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');

        return [
            'image' => $url,
            'info' => [
                ['Job', (string) $o['wo_no']],
                ['Product', self::productLabel($o)],
                ['Qty Order', Locale::number($woQty, 0).' '.$o['uom']],
                ['P × L × T', $card ? $mm($card->dim_l).' × '.$mm($card->dim_w).' × '.$mm($card->dim_h).' mm' : '—'],
                ['Datar', $card && $card->flat_w ? $mm($card->flat_w).' × '.$mm($card->flat_h).' mm' : '—'],
            ],
        ];
    }
}
