<?php

namespace App\Support\V2\Data\Concerns;

use App\Models\Master\EmpEmployee;
use App\Models\Master\FlowLog;
use App\Services\Flow\FlowEngineService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Bersama untuk dokumen ber-approval flow engine (PR, PO, ...): antrian approver, log History, konversi nilai form.
trait FlowDocument
{
    public static function pendingFor(string $module, int $docId): ?object
    {
        return app(FlowEngineService::class)->pendingForUser($module, Auth::id())->firstWhere('doc_id', $docId);
    }

    // flow_log -> tab History (terbaru di atas).
    public static function flowLog(string $module, int $docId): array
    {
        return FlowLog::where('module_code', $module)->where('doc_id', $docId)->orderByDesc('acted_at')->get()
            ->map(fn ($l) => ['at' => $l->acted_at?->toDateTimeString(), 'by' => $l->created_by, 'action' => ucfirst(strtolower((string) $l->action)), 'note' => $l->notes])->all();
    }

    // Jalur approval untuk nilai dokumen ini, meniru pemilihan langkah FlowEngineService (step_no naik, status_from =
    // status_to langkah sebelumnya, rentang nilai) -- jadi jumlah level ikut nilai (mis. PO > 1 jt = 3 level).
    public static function approvalPath(string $module, float $amount): Collection
    {
        $config = app(FlowEngineService::class)->activeConfig($module);
        if (! $config) {
            return collect();
        }
        $inRange = fn ($s) => ($s->min_amount === null || (float) $s->min_amount <= $amount) && ($s->max_amount === null || (float) $s->max_amount >= $amount);
        $steps = $config->steps()->orderBy('step_no')->get();
        $path = collect();
        $cur = $steps->first(fn ($s) => $s->status_from === 'SUBMITTED' && $s->step_type === 'APPROVAL' && $inRange($s));
        while ($cur && $cur->step_type === 'APPROVAL') {
            $path->push($cur);
            $cur = $steps->first(fn ($s) => $s->step_no > $cur->step_no && $s->status_from === $cur->status_to && $inRange($s));
        }

        return $path;
    }

    // Level approval dokumen: {levels, at (1..n, 0 = tidak sedang menunggu), held, steps: [role, by, at, status, plt]}.
    // Nama pemegang jabatan ikut ditampilkan supaya jelas siapa orangnya (user 2026-09-30).
    public static function approvalState(string $module, int $docId, float $amount): array
    {
        $logs = FlowLog::where('module_code', $module)->where('doc_id', $docId)->orderBy('flow_log_id')->get();
        $submit = $logs->where('action', 'SUBMITTED')->first();
        $path = self::approvalPath($module, $submit ? (float) $submit->amount_snapshot : $amount);
        $last = $logs->last();
        $positions = DB::connection('tm2mst')->table('org_position')->whereIn('position_id', $path->pluck('position_id'))->pluck('position_name', 'position_id');
        $holders = EmpEmployee::whereIn('position_id', $path->pluck('position_id'))->where('data_origin', '<>', 'DUMMY')->get(['position_id', 'full_name'])
            ->groupBy('position_id')->map(fn ($g) => $g->pluck('full_name')->map(fn ($n) => Str::title(strtolower($n)))->implode(', '));
        $at = 0;
        $steps = $path->values()->map(function ($s, $i) use ($logs, $last, $positions, $holders, &$at) {
            $done = $logs->where('flow_step_id', $s->flow_step_id)->whereIn('action', ['APPROVED', 'REJECTED'])->last();
            $pending = $last && $last->flow_step_id === $s->flow_step_id && in_array($last->action, ['SUBMITTED', 'HOLD'], true);
            if ($pending) {
                $at = $i + 1;
            }
            $who = $holders[$s->position_id] ?? '';

            return ['role' => ($positions[$s->position_id] ?? $s->label).($who ? ' — '.$who : ''),
                'by' => $done?->created_by, 'at' => $done?->acted_at?->format('d-M-Y H:i'), 'plt' => (bool) $done?->is_acting,
                'status' => $done ? ($done->action === 'APPROVED' ? 'done' : 'rejected') : ($pending ? 'current' : 'pending')];
        })->all();

        return ['levels' => count($steps), 'at' => $at, 'held' => $last?->action === 'HOLD', 'steps' => $steps,
            'submitted_by' => $submit?->created_by, 'submitted_at' => $submit?->acted_at?->format('d-M-Y H:i')];
    }

    private static function str(mixed $v): string
    {
        return $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : trim((string) $v);
    }

    private static function num(mixed $v): string
    {
        $v = trim((string) $v);
        $v = str_contains($v, ',') ? str_replace(['.', ','], ['', '.'], $v) : (substr_count($v, '.') > 1 ? str_replace('.', '', $v) : $v);

        return $v === '' ? '0' : $v;
    }

    private static function changed(string $v, mixed $cur): bool
    {
        $cur = self::str($cur);

        return $v !== $cur && ! (is_numeric($v) && is_numeric($cur) && (float) $v === (float) $cur);
    }
}
