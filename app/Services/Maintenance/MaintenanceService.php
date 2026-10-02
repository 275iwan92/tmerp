<?php

namespace App\Services\Maintenance;

use App\Models\Master\MatRaw;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdMachineDowntime;
use App\Models\Prd\MntLog;
use App\Models\Prd\MntMeterReading;
use App\Models\Prd\MntPlan;
use App\Models\Prd\MntRequest;
use App\Models\Prd\MntWo;
use App\Models\Prd\MntWoLabor;
use App\Models\Prd\MntWoPart;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysConstant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Aturan Maintenance (docs/maintenance_tier1.md §3-§5). Satu sumber untuk layar v2, command generator & test.
// Downtime & status mesin memakai tabel yang sudah ada (prod_machine_downtime, prod_machine.machine_status) -- tidak ada
// tabel downtime kedua, jadi Availability OEE ikut benar.
final class MaintenanceService
{
    public function __construct(private ?string $login = null)
    {
        $this->login ??= auth()->user()?->login ?? 'system';
    }

    // Keputusan (Accept/Reject request, Confirm/Close WO, Back to Draft) = hak approve modul MAINT; admin bebas.
    public static function canDecide(?SecUser $u): bool
    {
        return $u && ($u->is_admin || (bool) ($u->accessibleModules()->get('MAINT')?->can_approve));
    }

    // P1 = mesin berhenti (kecuali kritikalitas C = ada pengganti -> P2); tidak berhenti: Safety/Breakdown P2, Corrective P3, Improvement P4.
    public static function suggestPriority(ProdMachine $m, string $kind, bool $stopped): string
    {
        if ($stopped) {
            return $m->criticality === 'C' ? 'P2' : 'P1';
        }

        return match ($kind) {
            'SAFETY', 'BREAKDOWN' => 'P2', 'IMPROVEMENT' => 'P4', default => 'P3',
        };
    }

    private function docNo(string $prefix, string $model, string $column = 'doc_no'): string
    {
        $head = $prefix.'-'.now()->format('ym').'-';
        $last = $model::query()->where($column, 'like', $head.'%')->orderByDesc($column)->value($column);

        return $head.str_pad((string) ((int) substr((string) $last, -4) + 1), 4, '0', STR_PAD_LEFT);
    }

    // ---------------------------------------------------------------- Request

    public function createRequest(array $d): MntRequest
    {
        $m = ProdMachine::findOrFail((int) $d['machine_id']);
        $kind = $d['request_kind'] ?? 'BREAKDOWN';
        $stopped = (bool) ($d['machine_stopped'] ?? false);

        return DB::connection('tm2prd')->transaction(function () use ($d, $m, $kind, $stopped) {
            $req = MntRequest::create([
                'doc_no' => $this->docNo('MR', MntRequest::class), 'request_date' => now(), 'machine_id' => $m->machine_id,
                'request_kind' => $kind, 'priority' => $d['priority'] ?? self::suggestPriority($m, $kind, $stopped),
                'machine_stopped' => $stopped, 'stopped_at' => $stopped ? ($d['stopped_at'] ?? now()) : null,
                'symptom' => trim((string) $d['symptom']), 'reported_by' => $d['reported_by'] ?? null,
                'breakdown_event_id' => $d['breakdown_event_id'] ?? null, 'status' => 'OPEN', 'created_by' => $this->login, 'updated_by' => $this->login,
            ]);
            MntLog::add('REQUEST', $req->id_request, 'Created', $req->symptom);
            // Mesin berhenti = downtime tak terencana mulai saat itu juga, bukan saat teknisi datang.
            if ($stopped) {
                $dt = $this->openDowntime($m, 'BREAKDOWN', $req->stopped_at, $req->symptom);
                $req->update(['downtime_id' => $dt->downtime_id]);
            }
            if ($req->priority === 'P1') {
                $this->acceptRequest($req, null, null, 'Auto: P1 emergency');
            }

            return $req->refresh();
        });
    }

    public function acceptRequest(MntRequest $req, ?int $technicianId, ?string $plannedStart, string $note = ''): MntWo
    {
        $this->guard($req->status === 'OPEN', t('This request has already been decided.'));

        return DB::connection('tm2prd')->transaction(function () use ($req, $technicianId, $plannedStart, $note) {
            $wo = MntWo::create([
                'wo_type' => $req->request_kind === 'BREAKDOWN' ? 'BREAKDOWN' : 'CORRECTIVE', 'source' => 'REQUEST', 'request_id' => $req->id_request,
                'machine_id' => $req->machine_id, 'priority' => $req->priority, 'description' => mb_substr($req->symptom, 0, 255),
                'needs_machine_stop' => $req->machine_stopped || $req->request_kind === 'BREAKDOWN',
                'planned_start' => $plannedStart ?: now(), 'assigned_to' => $technicianId, 'downtime_id' => $req->downtime_id,
                'work_status' => 'DRAFT', 'created_by' => $this->login, 'updated_by' => $this->login,
            ]);
            MntLog::add('WO', $wo->id_wo, 'Created', 'From '.$req->doc_no);
            $this->confirm($wo);
            $req->update(['status' => 'ACCEPTED', 'wo_id' => $wo->id_wo, 'updated_by' => $this->login]);
            MntLog::add('REQUEST', $req->id_request, 'Accepted', trim($note.' -> '.$wo->refresh()->doc_no));

            return $wo;
        });
    }

    public function rejectRequest(MntRequest $req, string $reason): void
    {
        $this->guard($req->status === 'OPEN', t('This request has already been decided.'));
        $this->guard(trim($reason) !== '', t('Enter a reason to Reject.'));
        DB::connection('tm2prd')->transaction(function () use ($req, $reason) {
            if ($req->downtime_id) {
                $this->closeDowntime($req->downtime_id, 'Request rejected: '.$reason);
            }
            $req->update(['status' => 'REJECTED', 'reject_reason' => $reason, 'updated_by' => $this->login]);
            MntLog::add('REQUEST', $req->id_request, 'Rejected', $reason);
        });
    }

    // ---------------------------------------------------------------- Work Order

    public function createWo(array $d): MntWo
    {
        $wo = MntWo::create([
            'wo_type' => $d['wo_type'] ?? 'CORRECTIVE', 'source' => 'MANUAL', 'machine_id' => (int) $d['machine_id'],
            'priority' => $d['priority'] ?? 'P3', 'description' => $d['description'] ?? 'New work order',
            'needs_machine_stop' => (bool) ($d['needs_machine_stop'] ?? true), 'planned_start' => $d['planned_start'] ?? now()->addDay()->setTime(8, 0),
            'work_status' => 'DRAFT', 'created_by' => $this->login, 'updated_by' => $this->login,
        ]);
        MntLog::add('WO', $wo->id_wo, 'Created');

        return $wo;
    }

    // Header + checklist + spare part + jam kerja dari satu form. Draft = semua; sedang dikerjakan = hasil pekerjaan saja.
    public function saveWo(MntWo $wo, array $in): void
    {
        $draft = $wo->docStatus() === 'DRAFT';
        $this->guard($draft || $wo->isWorking(), t('This WO is locked (Scheduled/Done/Closed/Cancelled).'));
        $header = $draft
            ? ['wo_type', 'machine_id', 'priority', 'description', 'needs_machine_stop', 'planned_start', 'planned_end', 'est_minutes', 'assigned_to', 'vendor_id', 'component_code', 'problem_code', 'cause_code', 'action_code', 'findings', 'cost_vendor']
            : ['assigned_to', 'vendor_id', 'component_code', 'problem_code', 'cause_code', 'action_code', 'findings', 'cost_vendor', 'meter_at_done'];

        DB::connection('tm2prd')->transaction(function () use ($wo, $in, $header) {
            $data = [];
            foreach ($header as $f) {
                if (array_key_exists($f, $in)) {
                    $data[$f] = $in[$f] === '' ? null : $in[$f];
                }
            }
            if (array_key_exists('needs_machine_stop', $data) || ($wo->docStatus() === 'DRAFT' && array_key_exists('description', $in))) {
                $data['needs_machine_stop'] = ! empty($in['needs_machine_stop']);
            }
            $wo->fill($data + ['updated_by' => $this->login])->save();

            foreach (array_keys((array) ($in['remove'] ?? [])) as $id) {
                $wo->tasks()->whereKey((int) $id)->delete();
            }
            foreach ((array) ($in['tasks'] ?? []) as $id => $row) {
                $t = $wo->tasks()->whereKey((int) $id)->first();
                if (! $t) {
                    continue;
                }
                $result = ($row['result'] ?? '') ?: null;
                $t->fill(['task_text' => $row['task_text'] ?? $t->task_text, 'result' => $result,
                    'measured_value' => ($row['measured_value'] ?? '') === '' ? null : (float) $row['measured_value'], 'note' => $row['note'] ?? $t->note]);
                if ($result && ! $t->done_at) {
                    $t->fill(['done_by' => $this->login, 'done_at' => now()]);
                }
                $t->updated_by = $this->login;
                $t->save();
            }
            foreach (array_keys((array) ($in['remove_parts'] ?? [])) as $id) {
                $wo->parts()->whereKey((int) $id)->delete();
            }
            foreach ((array) ($in['parts'] ?? []) as $id => $row) {
                $p = $wo->parts()->whereKey((int) $id)->first();
                $p?->update(['qty_plan' => (float) ($row['qty_plan'] ?? $p->qty_plan), 'qty_used' => ($row['qty_used'] ?? '') === '' ? null : (float) $row['qty_used'], 'updated_by' => $this->login]);
            }
            foreach (array_keys((array) ($in['remove_labor'] ?? [])) as $id) {
                $wo->labor()->whereKey((int) $id)->delete();
            }
            foreach ((array) ($in['labor'] ?? []) as $id => $row) {
                $l = $wo->labor()->whereKey((int) $id)->first();
                $l?->update(['minutes' => (int) ($row['minutes'] ?? $l->minutes), 'work_date' => $row['work_date'] ?? $l->work_date, 'note' => $row['note'] ?? $l->note, 'updated_by' => $this->login]);
            }
            $this->recost($wo);
        });
    }

    public function addTask(MntWo $wo, string $text): void
    {
        $this->guard(trim($text) !== '', t('Enter the task description.'));
        $wo->tasks()->create(['seq_no' => (int) $wo->tasks()->max('seq_no') + 1, 'task_text' => trim($text), 'task_kind' => 'INSPECT',
            'is_required' => false, 'created_by' => $this->login, 'updated_by' => $this->login]);
    }

    public function addPart(MntWo $wo, int $materialId, float $qty = 1): void
    {
        $mat = MatRaw::findOrFail($materialId);
        $wo->parts()->create(['material_id' => $mat->material_id, 'qty_plan' => $qty, 'uom_code' => $mat->uom_stock ?: $mat->uom_purchase,
            'unit_cost' => (float) ($mat->avg_unitprice ?: $mat->last_unitprice ?: 0), 'created_by' => $this->login, 'updated_by' => $this->login]);
        $this->recost($wo);
    }

    public function addLabor(MntWo $wo, int $employeeId, int $minutes, ?string $date = null): void
    {
        $this->guard($minutes > 0, t('Enter the working minutes.'));
        $wo->labor()->create(['employee_id' => $employeeId, 'work_date' => $date ?: now()->toDateString(), 'minutes' => $minutes,
            'rate_snapshot' => SysConstant::number('MNT_LABOR_RATE_HOUR', 0), 'created_by' => $this->login, 'updated_by' => $this->login]);
        $this->recost($wo);
    }

    public function recost(MntWo $wo): void
    {
        $part = MntWoPart::where('wo_id', $wo->id_wo)->get()->sum(fn ($p) => (float) ($p->qty_used ?? $p->qty_plan) * (float) $p->unit_cost);
        $labor = MntWoLabor::where('wo_id', $wo->id_wo)->get()->sum(fn ($l) => $l->minutes / 60 * (float) $l->rate_snapshot);
        $wo->update(['cost_part' => round($part, 2), 'cost_labor' => round($labor, 2)]);
    }

    // Confirm = nomor MWO lahir, masuk jadwal teknisi (Scheduled).
    public function confirm(MntWo $wo): void
    {
        $this->guard($wo->docStatus() === 'DRAFT', t('Only a Draft WO can be confirmed.'));
        $this->guard((bool) $wo->machine_id && trim((string) $wo->description) !== '', t('A WO needs a machine and a description.'));
        $wo->update(['doc_no' => $wo->doc_no ?: $this->docNo('MWO', MntWo::class), 'stat_confirm' => true, 'work_status' => 'SCHEDULED', 'updated_by' => $this->login]);
        MntLog::add('WO', $wo->id_wo, 'Confirmed');
    }

    public function backToDraft(MntWo $wo): void
    {
        $this->guard($wo->docStatus() === 'OPEN' && $wo->work_status === 'SCHEDULED', t('Back to Draft is only for a Scheduled WO that has not started.'));
        $wo->update(['stat_confirm' => false, 'work_status' => 'DRAFT', 'updated_by' => $this->login]);
        MntLog::add('WO', $wo->id_wo, 'Back to Draft');
    }

    // Start: mesin berhenti -> downtime dibuka (atau lanjut yang dibuka saat laporan) + status mesin MAINTENANCE / BREAKDOWN.
    public function start(MntWo $wo): void
    {
        $this->guard($wo->docStatus() === 'OPEN' && $wo->work_status === 'SCHEDULED', t('Start is only for a Scheduled WO.'));
        DB::connection('tm2prd')->transaction(function () use ($wo) {
            $data = ['work_status' => 'IN_PROGRESS', 'actual_start' => now(), 'updated_by' => $this->login];
            if ($wo->needs_machine_stop) {
                $m = ProdMachine::findOrFail($wo->machine_id);
                $kind = $wo->wo_type === 'BREAKDOWN' ? 'BREAKDOWN' : 'PLANNED_MAINT';
                if (! $wo->downtime_id || ! ProdMachineDowntime::whereKey($wo->downtime_id)->whereNull('end_at')->exists()) {
                    $data['downtime_id'] = $this->openDowntime($m, $kind, now(), $wo->description)->downtime_id;
                }
                $m->update(['machine_status' => $kind === 'BREAKDOWN' ? 'BREAKDOWN' : 'MAINTENANCE']);
            }
            $wo->update($data);
            MntLog::add('WO', $wo->id_wo, 'Started');
        });
    }

    public function hold(MntWo $wo, string $note = ''): void
    {
        $this->guard($wo->docStatus() === 'OPEN' && $wo->work_status === 'IN_PROGRESS', t('Hold is only possible while In Progress.'));
        $wo->update(['work_status' => 'WAITING_PART', 'updated_by' => $this->login]);
        MntLog::add('WO', $wo->id_wo, 'Hold', $note ?: 'Waiting part');
    }

    public function resume(MntWo $wo): void
    {
        $this->guard($wo->docStatus() === 'OPEN' && $wo->work_status === 'WAITING_PART', t('Resume is only possible while Waiting Part.'));
        $wo->update(['work_status' => 'IN_PROGRESS', 'updated_by' => $this->login]);
        MntLog::add('WO', $wo->id_wo, 'Resumed');
    }

    // Done: checklist wajib terisi, downtime ditutup, mesin READY lagi. Biaya & plan dikunci saat Close oleh kepala maintenance.
    public function done(MntWo $wo): void
    {
        $this->guard($wo->isWorking(), t('Done is only for a WO in progress.'));
        $open = $wo->tasks()->where('is_required', true)->whereNull('result')->count();
        $this->guard($open === 0, tf('Cannot finish yet: :n required checklist task(s) have no result.', ['n' => $open]));
        DB::connection('tm2prd')->transaction(function () use ($wo) {
            if ($wo->downtime_id) {
                $this->closeDowntime($wo->downtime_id, trim(($wo->action_code ? $wo->action_code.': ' : '').(string) $wo->findings) ?: $wo->description, $wo);
            }
            if ($wo->needs_machine_stop) {
                ProdMachine::whereKey($wo->machine_id)->whereIn('machine_status', ['MAINTENANCE', 'BREAKDOWN'])->update(['machine_status' => 'READY']);
            }
            $wo->update(['work_status' => 'DONE', 'actual_end' => now(), 'updated_by' => $this->login]);
            $this->recost($wo);
            MntLog::add('WO', $wo->id_wo, 'Done');
        });
    }

    // Close (kepala maintenance): biaya final, plan maju ke jatuh tempo berikutnya, cache kolom mesin diperbarui.
    public function close(MntWo $wo): void
    {
        $this->guard($wo->docStatus() === 'OPEN' && $wo->work_status === 'DONE', t('Close is only for a Done WO.'));
        DB::connection('tm2prd')->transaction(function () use ($wo) {
            $this->recost($wo);
            $wo->update(['stat_close' => true, 'closed_by' => $this->login, 'closed_at' => now(), 'updated_by' => $this->login]);
            if ($wo->plan_id && ($plan = MntPlan::find($wo->plan_id))) {
                $doneAt = CarbonImmutable::parse($wo->actual_end ?? now());
                $meter = $wo->meter_at_done ?? ($plan->meter_type ? MntMeterReading::latest($wo->machine_id, $plan->meter_type) : null);
                $plan->update(['last_done_date' => $doneAt->toDateString(), 'last_done_meter' => $meter, 'updated_by' => $this->login]
                    + $this->nextDue($plan, $doneAt, $meter));
            }
            $this->refreshMachineCache($wo->machine_id);
            MntLog::add('WO', $wo->id_wo, 'Closed');
        });
    }

    public function cancel(MntWo $wo, string $reason): void
    {
        $this->guard(in_array($wo->docStatus(), ['OPEN'], true) && $wo->work_status !== 'DONE', t('Cancel is only for a Confirmed WO that is not Done.'));
        $this->guard(trim($reason) !== '', t('Enter a reason to Cancel.'));
        DB::connection('tm2prd')->transaction(function () use ($wo, $reason) {
            if ($wo->downtime_id) {
                $this->closeDowntime($wo->downtime_id, 'WO cancelled: '.$reason);
            }
            if ($wo->needs_machine_stop && $wo->actual_start) {
                ProdMachine::whereKey($wo->machine_id)->whereIn('machine_status', ['MAINTENANCE', 'BREAKDOWN'])->update(['machine_status' => 'READY']);
            }
            $wo->update(['stat_cancel' => true, 'cancel_reason' => $reason, 'updated_by' => $this->login]);
            MntLog::add('WO', $wo->id_wo, 'Cancelled', $reason);
        });
    }

    public function deleteDraft(MntWo $wo): void
    {
        $this->guard($wo->docStatus() === 'DRAFT', t('Only a Draft WO can be deleted.'));
        $this->guard(! $wo->request_id, t('A WO created from a Request cannot be deleted; use Cancel.'));
        $wo->delete();
    }

    // ---------------------------------------------------------------- Preventive

    // Jatuh tempo berikutnya. FLOATING dari tanggal selesai terakhir; FIXED dari jadwal sebelumnya (tanggal pasti, mis. inspeksi K3).
    public function nextDue(MntPlan $plan, ?CarbonImmutable $doneAt = null, ?float $meter = null): array
    {
        $out = ['next_due_date' => null, 'next_due_meter' => null];
        if ($plan->usesTime()) {
            $base = $plan->schedule_mode === 'FIXED' && $plan->next_due_date
                ? CarbonImmutable::parse($plan->next_due_date)
                : ($doneAt ?? ($plan->last_done_date ? CarbonImmutable::parse($plan->last_done_date) : CarbonImmutable::today()));
            $next = $this->addInterval($base, (int) $plan->interval_value, (string) $plan->interval_unit);
            // FIXED yang tertinggal jauh tidak menumpuk: lompat ke periode yang belum lewat.
            while ($plan->schedule_mode === 'FIXED' && $next->lt(CarbonImmutable::today())) {
                $next = $this->addInterval($next, (int) $plan->interval_value, (string) $plan->interval_unit);
            }
            $out['next_due_date'] = $next->toDateString();
        }
        if ($plan->usesMeter()) {
            $out['next_due_meter'] = ($meter ?? (float) $plan->last_done_meter) + (float) $plan->meter_interval;
        }

        return $out;
    }

    private function addInterval(CarbonImmutable $d, int $n, string $unit): CarbonImmutable
    {
        return match ($unit) {
            'DAY' => $d->addDays($n), 'WEEK' => $d->addWeeks($n), 'YEAR' => $d->addYearsNoOverflow($n), default => $d->addMonthsNoOverflow($n),
        };
    }

    // Plan jatuh tempo (tanggal <= hari ini + lead_days, atau meter sudah lewat batas) dan belum punya WO terbuka.
    public function isDue(MntPlan $plan, ?CarbonImmutable $today = null): bool
    {
        $today ??= CarbonImmutable::today();
        $byTime = $plan->usesTime() && $plan->next_due_date && CarbonImmutable::parse($plan->next_due_date)->subDays((int) $plan->lead_days)->lte($today);
        $cur = $plan->usesMeter() ? MntMeterReading::latest($plan->machine_id, $plan->meter_type) : null;
        $byMeter = $plan->usesMeter() && $cur !== null && $plan->next_due_meter !== null && $cur >= (float) $plan->next_due_meter;

        return $byTime || $byMeter;
    }

    public function generateFromPlan(MntPlan $plan, bool $force = false): ?MntWo
    {
        $open = MntWo::where('plan_id', $plan->id_plan)->where('stat_cancel', false)->where('stat_close', false)->exists();
        if ($open || ! $plan->activate || (! $force && ! $this->isDue($plan))) {
            return null;
        }

        return DB::connection('tm2prd')->transaction(function () use ($plan) {
            $start = CarbonImmutable::parse($plan->next_due_date ?? now()->addDay())->setTime(7, 0);
            if ($start->lt(now())) {
                $start = CarbonImmutable::tomorrow()->setTime(7, 0);
            }
            $wo = MntWo::create([
                'wo_type' => $plan->wo_type, 'source' => 'PLAN', 'plan_id' => $plan->id_plan, 'machine_id' => $plan->machine_id, 'priority' => 'P3',
                'description' => mb_substr($plan->plan_name, 0, 255), 'needs_machine_stop' => $plan->needs_machine_stop,
                'planned_start' => $start, 'planned_end' => $plan->est_minutes ? $start->addMinutes((int) $plan->est_minutes) : null,
                'est_minutes' => $plan->est_minutes, 'vendor_id' => $plan->vendor_id, 'work_status' => 'DRAFT',
                'created_by' => $this->login, 'updated_by' => $this->login,
            ]);
            foreach ($plan->tasks as $t) {
                $wo->tasks()->create($t->only(['seq_no', 'task_text', 'task_kind', 'measure_uom', 'min_value', 'max_value', 'is_required'])
                    + ['created_by' => $this->login, 'updated_by' => $this->login]);
            }
            foreach ($plan->parts as $p) {
                $mat = MatRaw::find($p->material_id);
                $wo->parts()->create(['material_id' => $p->material_id, 'qty_plan' => $p->qty, 'uom_code' => $p->uom_code ?: $mat?->uom_stock,
                    'unit_cost' => (float) ($mat?->avg_unitprice ?: $mat?->last_unitprice ?: 0), 'created_by' => $this->login, 'updated_by' => $this->login]);
            }
            $this->recost($wo);
            MntLog::add('WO', $wo->id_wo, 'Created', 'Generated from '.$plan->plan_code);

            return $wo;
        });
    }

    // Semua plan aktif yang jatuh tempo -> WO Draft (kepala maintenance mengatur jadwal lalu Confirm). Balasan = WO baru.
    public function generateDue(): array
    {
        $made = [];
        foreach (MntPlan::where('activate', true)->with(['tasks', 'parts'])->get() as $plan) {
            if ($wo = $this->generateFromPlan($plan)) {
                $made[] = $wo;
            }
        }

        return $made;
    }

    // ---------------------------------------------------------------- Mesin

    private function openDowntime(ProdMachine $m, string $kind, $at, string $cause): ProdMachineDowntime
    {
        $open = ProdMachineDowntime::where('machine_id', $m->machine_id)->whereNull('end_at')->where('downtime_kind', $kind)->first();

        return $open ?? ProdMachineDowntime::create([
            'machine_id' => $m->machine_id, 'downtime_kind' => $kind, 'is_planned' => ProdMachineDowntime::KINDS[$kind]['planned'],
            'start_at' => $at, 'root_cause' => mb_substr($cause, 0, 255), 'reported_by' => $this->login,
            'created_by' => $this->login, 'updated_by' => $this->login,
        ]);
    }

    private function closeDowntime(int $id, string $action, ?MntWo $wo = null): void
    {
        $dt = ProdMachineDowntime::whereKey($id)->whereNull('end_at')->first();
        $dt?->update(['end_at' => now(), 'action_taken' => mb_substr($action, 0, 255), 'repair_cost' => $wo?->totalCost(), 'updated_by' => $this->login]);
    }

    // Kolom lama prod_machine (last/next maintenance, MTBF) = cache hasil hitung dari plan & WO, bukan diketik manual.
    private function refreshMachineCache(int $machineId): void
    {
        $last = MntWo::where('machine_id', $machineId)->where('stat_close', true)->max('actual_end');
        $next = MntPlan::where('machine_id', $machineId)->where('activate', true)->whereNotNull('next_due_date')->min('next_due_date');
        $rel = self::reliability($machineId, 90);
        ProdMachine::whereKey($machineId)->update(array_filter([
            'last_maintenance_date' => $last ? substr((string) $last, 0, 10) : null, 'next_maintenance_date' => $next,
            'mtbf_hours' => $rel['mtbf'],
        ], fn ($v) => $v !== null));
    }

    // MTBF / MTTR / Availability dari downtime BREAKDOWN dalam N hari terakhir (jam kalender, 24 jam).
    public static function reliability(int $machineId, int $days = 90): array
    {
        $from = now()->subDays($days);
        $rows = ProdMachineDowntime::where('machine_id', $machineId)->where('downtime_kind', 'BREAKDOWN')->where('start_at', '>=', $from)->get();
        $n = $rows->count();
        $down = $rows->sum(fn ($d) => ($d->end_at ?? now())->diffInMinutes($d->start_at, true)) / 60;
        $period = $days * 24;

        return ['breakdowns' => $n, 'mtbf' => $n ? round(($period - $down) / $n, 1) : null, 'mttr' => $n ? round($down / $n, 1) : null,
            'availability' => round(($period - $down) / $period * 100, 1)];
    }

    private function guard(bool $ok, string $msg): void
    {
        if (! $ok) {
            throw ValidationException::withMessages(['maintenance' => $msg]);
        }
    }
}
