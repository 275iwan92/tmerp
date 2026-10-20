<?php

declare(strict_types=1);

/* REMARK
 * Tujuan       : Breakdown mesin pada baris proses Work Order dan keputusan lanjutannya: lapor breakdown (RUNNING -> BREAKDOWN + Maintenance Request), pindah ke mesin alternatif (-> PENDING, kembali ke antrean) atau subkontrak (PO Subkontrak lewat SubcontractPoService, minimal Manager Operasional).
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::production.wo-entry (submitBreakdownReport, rerouteToMachine, createSubcontractPo); Tier1 9.3 dan Tier2 7.
 * Input        : id operasi / id event, estimasi downtime (menit), mesin alternatif, vendor, estimasi biaya, tanggal kirim dan kembali, user.
 * Output       : prd_breakdown_event (+ reroute_decision, alt_machine_id), prd_wo_operation (task_status, machine_id), PO subkontrak (tm2pur) dan Maintenance Request (gagal membuatnya tidak menghentikan laporan produksi, dicatat lewat report()).
 * Dipakai oleh : routes/v2_production.php (v2.production.wo.op.*), layar production-wo (kartu Execution), tes WoExecutionServiceTest.
 * Status       : Aktif (T9 pindahan v1, 2026-10-20).
 */

namespace App\Services\Production;

use App\Models\Prd\PrdBreakdownEvent;
use App\Models\Prd\PrdWoOperation;
use App\Models\Sys\SecUser;
use App\Services\Maintenance\RequestService;
use App\Services\Pur\SubcontractPoService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class WoBreakdownService
{
    private function move(PrdWoOperation $op, string $target): void
    {
        try {
            $op->transitionTo($target);
        } catch (DomainException) {
            throw ValidationException::withMessages(['document' => tf('This task is :from and cannot move to :to.', ['from' => $op->task_status, 'to' => $target])]);
        }
    }

    public function report(int $operationId, int $downtimeMinutes, SecUser $user): PrdBreakdownEvent
    {
        if ($downtimeMinutes < 1) {
            throw ValidationException::withMessages(['downtime' => 'Estimated downtime must be at least 1 minute.']);
        }

        $event = DB::connection('tm2prd')->transaction(function () use ($operationId, $downtimeMinutes, $user) {
            $op = PrdWoOperation::lockForUpdate()->findOrFail($operationId);
            $event = PrdBreakdownEvent::create(['operation_id' => $op->operation_id, 'machine_id' => $op->machine_id, 'reported_by' => $user->login,
                'reported_at' => now(), 'downtime_estimate_minutes' => $downtimeMinutes]);
            $this->move($op, 'BREAKDOWN');

            return $event;
        });

        // Maintenance ikut tahu: laporan ini menjadi Maintenance Request (P1 langsung jadi BMO). Gagal di sini tidak menghentikan laporan produksi.
        try {
            (new RequestService($user->login))->fromBreakdown((int) $event->event_id, (int) $event->machine_id, 'Operation #'.$event->operation_id.'.');
        } catch (Throwable $e) {
            report($e);
        }

        return $event;
    }

    /** Event breakdown terbuka (belum ada keputusan) milik satu operasi yang masih BREAKDOWN. */
    public function openEvent(int $operationId): ?PrdBreakdownEvent
    {
        return PrdBreakdownEvent::where('operation_id', $operationId)->whereNull('reroute_decision')->orderByDesc('event_id')->first();
    }

    public function rerouteToMachine(int $operationId, int $altMachineId, SecUser $user): PrdWoOperation
    {
        if ($altMachineId <= 0) {
            throw ValidationException::withMessages(['alt_machine_id' => 'Choose an alternative machine first.']);
        }

        return DB::connection('tm2prd')->transaction(function () use ($operationId, $altMachineId, $user) {
            $op = PrdWoOperation::lockForUpdate()->findOrFail($operationId);
            $event = $this->openEvent($operationId) ?? throw ValidationException::withMessages(['document' => 'This task has no open breakdown report.']);
            $op->machine_id = $altMachineId;
            $op->updated_by = $user->login;
            $this->move($op, 'PENDING');
            $event->update(['reroute_decision' => 'MACHINE_ALT', 'alt_machine_id' => $altMachineId]);

            return $op;
        });
    }

    /** Subkontrak butuh approval minimal Manager Operasional (Tier1 9.3). */
    public function createSubcontractPo(int $operationId, int $vendorId, float $estimatedCost, string $dueOut, string $dueReturn, SecUser $user)
    {
        if (! $user->isOpsManagerOrAbove()) {
            throw new AuthorizationException('Only an Operations Manager or above can approve a subcontract PO.');
        }
        if ($vendorId <= 0 || $estimatedCost <= 0 || $dueOut === '' || $dueReturn === '') {
            throw ValidationException::withMessages(['document' => 'Complete the vendor, estimated cost, and send / return dates first.']);
        }
        $event = $this->openEvent($operationId) ?? throw ValidationException::withMessages(['document' => 'This task has no open breakdown report.']);

        return app(SubcontractPoService::class)->createFromBreakdown($event, $vendorId, $estimatedCost, Carbon::parse($dueOut), Carbon::parse($dueReturn), $user->login, $user->name ?? $user->login);
    }
}
