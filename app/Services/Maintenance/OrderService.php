<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Master\MatRaw;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdMachineDowntime;
use App\Models\Mnt\MntLog;
use App\Models\Mnt\MntOrder;
use App\Models\Mnt\MntOrderLabor;
use App\Models\Mnt\MntOrderPart;
use App\Models\Sys\SysConstant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Siklus Maintenance Order (PMO, BMO, IMO): Draft -> Confirm (Scheduled) -> Start -> Hold / Resume -> Done -> Close, atau Cancel.
// Aset yang tertaut ke Master Mesin membuka dan menutup downtime (satu tabel downtime milik Master Mesin) dan mengubah status mesin;
// kendaraan dan fasilitas tidak punya downtime mesin. Memutuskan (Confirm, Close, Cancel) = kepala maintenance; mengerjakan = teknisi.
final class OrderService
{
    public function __construct(private ?string $login = null)
    {
        $this->login ??= auth()->user()?->login ?? 'system';
    }

    public function create(array $d): MntOrder
    {
        $order = MntOrder::create([
            'order_type' => $d['order_type'], 'source' => 'MANUAL', 'asset_id' => (int) $d['asset_id'], 'priority' => $d['priority'] ?? 'P3',
            'description' => $d['description'] ?? 'New order', 'needs_stop' => (bool) ($d['needs_stop'] ?? true),
            'planned_start' => $d['planned_start'] ?? now()->addDay()->setTime(8, 0), 'work_status' => 'DRAFT', 'created_by' => $this->login, 'updated_by' => $this->login,
        ]);
        MntLog::add('ORDER', $order->id_order, 'Created');

        return $order;
    }

    // Header, checklist, spare part dan jam kerja dari satu form. Draft = semua; sedang dikerjakan = hasil pekerjaan saja.
    public function save(MntOrder $order, array $in): void
    {
        $draft = $order->docStatus() === 'DRAFT';
        $this->guard($draft || $order->isWorking(), t('This order is locked (Scheduled, Done, Closed or Cancelled).'));
        $header = $draft
            ? ['order_type', 'asset_id', 'priority', 'description', 'needs_stop', 'planned_start', 'planned_end', 'est_minutes', 'assigned_to', 'vendor_id', 'component_code', 'problem_code', 'cause_code', 'action_code', 'findings', 'cost_vendor']
            : ['assigned_to', 'vendor_id', 'component_code', 'problem_code', 'cause_code', 'action_code', 'findings', 'cost_vendor', 'meter_at_done'];

        DB::connection('tm2mnt')->transaction(function () use ($order, $in, $header, $draft) {
            $data = [];
            foreach ($header as $f) {
                if (array_key_exists($f, $in)) {
                    $data[$f] = $in[$f] === '' ? null : $in[$f];
                }
            }
            if ($draft && array_key_exists('description', $in)) {
                $data['needs_stop'] = ! empty($in['needs_stop']);
            }
            $order->fill($data + ['updated_by' => $this->login])->save();

            foreach (array_keys((array) ($in['remove'] ?? [])) as $id) {
                $order->tasks()->whereKey((int) $id)->delete();
            }
            foreach ((array) ($in['tasks'] ?? []) as $id => $row) {
                $t = $order->tasks()->whereKey((int) $id)->first();
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
                $order->parts()->whereKey((int) $id)->delete();
            }
            foreach ((array) ($in['parts'] ?? []) as $id => $row) {
                $order->parts()->whereKey((int) $id)->first()?->update(['qty_plan' => (float) ($row['qty_plan'] ?? 1),
                    'qty_used' => ($row['qty_used'] ?? '') === '' ? null : (float) $row['qty_used'], 'updated_by' => $this->login]);
            }
            foreach (array_keys((array) ($in['remove_labor'] ?? [])) as $id) {
                $order->labor()->whereKey((int) $id)->delete();
            }
            foreach ((array) ($in['labor'] ?? []) as $id => $row) {
                $order->labor()->whereKey((int) $id)->first()?->update(['minutes' => max(1, (int) ($row['minutes'] ?? 1)), 'work_date' => $row['work_date'] ?? now()->toDateString(),
                    'note' => $row['note'] ?? null, 'updated_by' => $this->login]);
            }
            $this->recost($order);
        });
    }

    public function addTask(MntOrder $order, string $text): void
    {
        $this->guard(trim($text) !== '', t('Enter the task description.'));
        $order->tasks()->create(['seq_no' => (int) $order->tasks()->max('seq_no') + 1, 'task_text' => trim($text), 'task_kind' => 'INSPECT',
            'is_required' => false, 'created_by' => $this->login, 'updated_by' => $this->login]);
    }

    public function addPart(MntOrder $order, int $materialId, float $qty = 1): void
    {
        $mat = MatRaw::findOrFail($materialId);
        $order->parts()->create(['material_id' => $mat->material_id, 'qty_plan' => max(0.001, $qty), 'uom_code' => $mat->uom_stock ?: $mat->uom_purchase,
            'unit_cost' => (float) ($mat->avg_unitprice ?: $mat->last_unitprice ?: 0), 'created_by' => $this->login, 'updated_by' => $this->login]);
        $this->recost($order);
    }

    public function addLabor(MntOrder $order, int $employeeId, int $minutes, ?string $date = null): void
    {
        $this->guard($minutes > 0, t('Enter the working minutes.'));
        $order->labor()->create(['employee_id' => $employeeId, 'work_date' => $date ?: now()->toDateString(), 'minutes' => $minutes,
            'rate_snapshot' => SysConstant::number('MNT_LABOR_RATE_HOUR', 0), 'created_by' => $this->login, 'updated_by' => $this->login]);
        $this->recost($order);
    }

    public function recost(MntOrder $order): void
    {
        $part = MntOrderPart::where('order_id', $order->id_order)->get()->sum(fn ($p) => (float) ($p->qty_used ?? $p->qty_plan) * (float) $p->unit_cost);
        $labor = MntOrderLabor::where('order_id', $order->id_order)->get()->sum(fn ($l) => $l->minutes / 60 * (float) $l->rate_snapshot);
        $order->update(['cost_part' => round($part, 2), 'cost_labor' => round($labor, 2)]);
    }

    // Confirm = nomor (PMO / BMO / IMO) lahir dan order masuk jadwal teknisi (Scheduled).
    public function confirm(MntOrder $order): void
    {
        $this->guard($order->docStatus() === 'DRAFT', t('Only a Draft order can be confirmed.'));
        $this->guard((bool) $order->asset_id && trim((string) $order->description) !== '', t('An order needs an asset and a description.'));
        $order->update(['doc_no' => $order->doc_no ?: DocNumber::next(MntOrder::prefixOf($order->order_type), MntOrder::class), 'stat_confirm' => true,
            'work_status' => 'SCHEDULED', 'updated_by' => $this->login]);
        MntLog::add('ORDER', $order->id_order, 'Confirmed');
    }

    public function backToDraft(MntOrder $order): void
    {
        $this->guard($order->docStatus() === 'OPEN' && $order->work_status === 'SCHEDULED', t('Back to Draft is only for a Scheduled order that has not started.'));
        $order->update(['stat_confirm' => false, 'work_status' => 'DRAFT', 'updated_by' => $this->login]);
        MntLog::add('ORDER', $order->id_order, 'Back to Draft');
    }

    // Start: bila aset mesin dan perlu berhenti -> downtime dibuka dan status mesin berubah.
    public function start(MntOrder $order): void
    {
        $this->guard($order->docStatus() === 'OPEN' && $order->work_status === 'SCHEDULED', t('Start is only for a Scheduled order.'));
        DB::connection('tm2mnt')->transaction(function () use ($order) {
            $data = ['work_status' => 'IN_PROGRESS', 'actual_start' => now(), 'updated_by' => $this->login];
            $machine = $this->machineOf($order);
            if ($machine && $order->needs_stop) {
                $kind = $order->order_type === 'BREAKDOWN' ? 'BREAKDOWN' : 'PLANNED_MAINT';
                if (! $order->downtime_id || ! ProdMachineDowntime::whereKey($order->downtime_id)->whereNull('end_at')->exists()) {
                    $data['downtime_id'] = $this->openDowntime($machine, $kind, $order->description)->downtime_id;
                }
                $machine->update(['machine_status' => $kind === 'BREAKDOWN' ? 'BREAKDOWN' : 'MAINTENANCE']);
            }
            $order->update($data);
            MntLog::add('ORDER', $order->id_order, 'Started');
        });
    }

    public function hold(MntOrder $order, string $note = ''): void
    {
        $this->guard($order->docStatus() === 'OPEN' && $order->work_status === 'IN_PROGRESS', t('Hold is only possible while In Progress.'));
        $order->update(['work_status' => 'WAITING_PART', 'updated_by' => $this->login]);
        MntLog::add('ORDER', $order->id_order, 'Hold', $note ?: 'Waiting part');
    }

    public function resume(MntOrder $order): void
    {
        $this->guard($order->docStatus() === 'OPEN' && $order->work_status === 'WAITING_PART', t('Resume is only possible while Waiting Part.'));
        $order->update(['work_status' => 'IN_PROGRESS', 'updated_by' => $this->login]);
        MntLog::add('ORDER', $order->id_order, 'Resumed');
    }

    // Done: checklist wajib terisi, downtime ditutup, mesin READY lagi. Biaya dan plan dikunci saat Close.
    public function done(MntOrder $order): void
    {
        $this->guard($order->isWorking(), t('Done is only for an order in progress.'));
        $open = $order->tasks()->where('is_required', true)->whereNull('result')->count();
        $this->guard($open === 0, tf('Cannot finish yet: :n required checklist task(s) have no result.', ['n' => $open]));
        DB::connection('tm2mnt')->transaction(function () use ($order) {
            if ($order->downtime_id) {
                $this->closeDowntime((int) $order->downtime_id, trim(($order->action_code ? $order->action_code.': ' : '').(string) $order->findings) ?: $order->description, $order);
            }
            $this->releaseMachine($order);
            $order->update(['work_status' => 'DONE', 'actual_end' => now(), 'updated_by' => $this->login]);
            $this->recost($order);
            MntLog::add('ORDER', $order->id_order, 'Done');
        });
    }

    // Close (kepala maintenance): biaya final dan plan maju ke jatuh tempo berikutnya.
    public function close(MntOrder $order): void
    {
        $this->guard($order->docStatus() === 'OPEN' && $order->work_status === 'DONE', t('Close is only for a Done order.'));
        DB::connection('tm2mnt')->transaction(function () use ($order) {
            $this->recost($order);
            $order->update(['stat_close' => true, 'closed_by' => $this->login, 'closed_at' => now(), 'updated_by' => $this->login]);
            (new PlanService($this->login))->afterClose($order->refresh());
            MntLog::add('ORDER', $order->id_order, 'Closed');
        });
    }

    public function cancel(MntOrder $order, string $reason): void
    {
        $this->guard($order->docStatus() === 'OPEN' && $order->work_status !== 'DONE', t('Cancel is only for a Confirmed order that is not Done.'));
        $this->guard(trim($reason) !== '', t('Enter a reason to Cancel.'));
        DB::connection('tm2mnt')->transaction(function () use ($order, $reason) {
            if ($order->downtime_id) {
                $this->closeDowntime((int) $order->downtime_id, 'Order cancelled: '.$reason);
            }
            if ($order->actual_start) {
                $this->releaseMachine($order);
            }
            $order->update(['stat_cancel' => true, 'cancel_reason' => $reason, 'updated_by' => $this->login]);
            MntLog::add('ORDER', $order->id_order, 'Cancelled', $reason);
        });
    }

    public function deleteDraft(MntOrder $order): void
    {
        $this->guard($order->docStatus() === 'DRAFT', t('Only a Draft order can be deleted.'));
        $this->guard(! $order->request_id, t('An order created from a Request cannot be deleted; use Cancel.'));
        $order->delete();
    }

    private function machineOf(MntOrder $order): ?ProdMachine
    {
        $machineId = $order->asset?->machine_id;

        return $machineId ? ProdMachine::find($machineId) : null;
    }

    private function releaseMachine(MntOrder $order): void
    {
        if ($order->needs_stop && ($machineId = $order->asset?->machine_id)) {
            ProdMachine::whereKey($machineId)->whereIn('machine_status', ['MAINTENANCE', 'BREAKDOWN'])->update(['machine_status' => 'READY']);
        }
    }

    private function openDowntime(ProdMachine $m, string $kind, string $cause): ProdMachineDowntime
    {
        $open = ProdMachineDowntime::where('machine_id', $m->machine_id)->whereNull('end_at')->where('downtime_kind', $kind)->first();

        return $open ?? ProdMachineDowntime::create([
            'machine_id' => $m->machine_id, 'downtime_kind' => $kind, 'is_planned' => ProdMachineDowntime::KINDS[$kind]['planned'],
            'start_at' => now(), 'root_cause' => mb_substr($cause, 0, 255), 'reported_by' => $this->login, 'created_by' => $this->login, 'updated_by' => $this->login,
        ]);
    }

    private function closeDowntime(int $id, string $action, ?MntOrder $order = null): void
    {
        ProdMachineDowntime::whereKey($id)->whereNull('end_at')->first()
            ?->update(['end_at' => now(), 'action_taken' => mb_substr($action, 0, 255), 'repair_cost' => $order?->totalCost(), 'updated_by' => $this->login]);
    }

    private function guard(bool $ok, string $msg): void
    {
        if (! $ok) {
            throw ValidationException::withMessages(['maintenance' => $msg]);
        }
    }
}
