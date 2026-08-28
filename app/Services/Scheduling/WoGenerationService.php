<?php

namespace App\Services\Scheduling;

use App\Models\Master\ProdPartProcessMachineRate;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use App\Models\Sdi\CoOrderDelivery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * F1 Job Order Generation -- production_scheduling_tier1.md §5, DIPICU dari
 * Plan Delivery (bukan CO langsung -- lihat
 * docs/production_scheduling_wo_generation_delta.md §1). BELUM PERNAH
 * diimplementasikan sebelum sesi ini -- seluruh prd_wo yang ada sebelumnya
 * adalah dummy data manual.
 *
 * 1 iqp_part = 1 prd_wo (keputusan user, delta doc §2) -- konvergensi
 * antar-part SENGAJA belum dimodelkan.
 */
class WoGenerationService
{
    public function __construct(
        private readonly BackwardSchedulingService $backwardScheduler,
    ) {}

    /**
     * @return Collection<int, PrdWo>
     *
     * @throws \RuntimeException kalau delivery ini sudah pernah generate WO,
     *                            atau tidak py inquiry/part sama sekali.
     */
    public function generateFromDelivery(CoOrderDelivery $delivery): Collection
    {
        if (PrdWo::where('source_delivery_id', $delivery->id_delivery)->exists()) {
            throw new \RuntimeException("Delivery #{$delivery->id_delivery} sudah pernah generate WO -- tidak boleh dobel.");
        }

        $delivery->loadMissing(['item.inquiry.parts.processes', 'item.inquiry.parts.materials']);
        $inquiry = $delivery->item?->inquiry;

        if (! $inquiry) {
            throw new \RuntimeException("Delivery #{$delivery->id_delivery}: item tidak terhubung ke Inquiry manapun, tidak ada BOM/BOP utk digenerate.");
        }

        if ($inquiry->parts->isEmpty()) {
            throw new \RuntimeException("Inquiry #{$inquiry->id_inquiry}: belum py part apa pun (iqp_part kosong).");
        }

        return DB::connection('tm2prd')->transaction(function () use ($delivery, $inquiry) {
            $created = collect();

            foreach ($inquiry->parts as $part) {
                $qty = (float) $delivery->ship_qty * (float) ($part->qty_per_sku ?: 1);

                $wo = PrdWo::create([
                    'wo_no' => PrdWo::nextWoNo(),
                    'wo_type' => 'MTO',
                    'source_type' => 'CO_CONFIRMED',
                    'source_co_id' => $delivery->order_id,
                    'source_delivery_id' => $delivery->id_delivery,
                    'source_iqp_part_id' => $part->id_part,
                    'product_id' => $inquiry->parent_product_id,
                    'part_code' => $part->part_code ?: $part->part_name,
                    'qty' => $qty,
                    'due_date' => $delivery->ship_date,
                    'wo_status' => 'DRAFT',
                    'schedule_layer' => 'SIMULATED',
                    'created_by' => 'system',
                ]);

                foreach ($part->processes->sortBy('step_seq') as $routingStep) {
                    $targetRate = $this->lookupDefaultMachineRate(
                        $inquiry->parent_product_id, $wo->part_code, $routingStep->process_id, $routingStep->machine_id,
                    );

                    PrdWoOperation::create([
                        'wo_id' => $wo->wo_id,
                        'seq_no' => $routingStep->step_seq,
                        'process_name' => $routingStep->process_name,
                        'resource_type' => $routingStep->machine_id ? 'MACHINE' : 'MANUAL',
                        'machine_id' => $routingStep->machine_id,
                        'qty' => $qty,
                        'task_status' => 'PENDING',
                        'rate_band_value' => $targetRate,
                        'created_by' => 'system',
                    ]);
                }

                // Backward-schedule & PERSIST saat generate (2026-08-28: schedule()
                // sebelumnya dipanggil di objek fresh() lalu hasilnya dibuang --
                // Est. Mulai/Selesai & material_needed_by tak pernah benar2 terisi
                // walau komentar lama bilang begitu, lihat CLAUDE.md/project memory
                // "engine MPS auto". WO tetap DRAFT (persist di sini cuma isi jadwal
                // awal, bukan approval) -- PPIC boleh override manual di WO
                // Detail/Gantt/Kanban kapan saja sesudahnya.
                if ($wo->operations()->exists()) {
                    try {
                        $this->backwardScheduler->scheduleAndPersist($wo);
                    } catch (\RuntimeException) {
                        // Rate band blm lengkap (task manual, dst) -- PPIC lengkapi
                        // manual di Screen #2, jangan gagalkan generate karena ini.
                    }
                }

                $created->push($wo);
            }

            return $created;
        });
    }

    private function lookupDefaultMachineRate(?int $productId, ?string $partCode, ?int $processId, ?int $machineId): ?float
    {
        if (! $productId || ! $partCode || ! $processId || ! $machineId) {
            return null;
        }

        return ProdPartProcessMachineRate::active()
            ->where('product_id', $productId)
            ->where('part_code', $partCode)
            ->where('process_id', $processId)
            ->where('machine_id', $machineId)
            ->value('target_qty_per_hour');
    }
}
