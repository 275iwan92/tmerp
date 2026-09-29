<?php

namespace App\Services\Scheduling;

use App\Models\Master\ProdPart;
use App\Models\Master\ProdPartProcessMachineRate;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoMaterial;
use App\Models\Prd\PrdWoOperation;
use App\Models\Sdi\CoOrderDelivery;
use App\Services\ProDev\SpecEngine;
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
        private readonly SpecEngine $spec,
    ) {}

    /**
     * @return Collection<int, PrdWo>
     *
     * @throws \RuntimeException kalau delivery ini sudah pernah generate WO,
     *                           atau tidak py inquiry/part sama sekali.
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

        // Syarat urutan dokumen (instruksi user 2026-09-18): WO baru boleh lahir
        // setelah Inquiry BERHARGA (costing dipublish = disetujui Direksi) dan
        // CO-nya CONFIRMED. Tanpa ini WO bisa jalan mendahului harga.
        if ($delivery->order?->doc_status !== 'CONFIRMED') {
            throw new \RuntimeException("CO delivery #{$delivery->id_delivery} belum CONFIRMED -- WO belum boleh dibuat.");
        }

        $priced = DB::connection('tm2sdi')->table('iqp_costing')
            ->where('inquiry_id', $inquiry->id_inquiry)
            ->where(fn ($q) => $q->where('published_flag', 1)->orWhere('is_approved', 1))
            ->exists();

        if (! $priced) {
            throw new \RuntimeException("Inquiry {$inquiry->inquiry_no}: costing belum dipublish/disetujui -- harga belum final, WO belum boleh dibuat.");
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
                    'product_id' => $inquiry->product_id,
                    'part_code' => $part->part_code ?: $part->part_name,
                    'qty' => $qty,
                    'due_date' => $delivery->ship_date,
                    'wo_status' => 'DRAFT',
                    'schedule_layer' => 'SIMULATED',
                    'created_by' => 'system',
                ]);

                // BOP & BOM dari ProDev (hasil engine atau override officer) --
                // dihitung untuk qty WO ini, lengkap dgn waste/toleransi/target.
                $prodPart = $part->prod_part_id ? ProdPart::find($part->prod_part_id) : null;
                $spec = $prodPart ? $this->spec->forWorkOrder($prodPart, $part, $qty) : ['bop' => [], 'bom' => []];

                if ($spec['bop']) {
                    foreach ($spec['bop'] as $i => $step) {
                        PrdWoOperation::create([
                            'wo_id' => $wo->wo_id,
                            'seq_no' => ($i + 1) * 10,
                            'process_name' => $step['label'],
                            'resource_type' => $step['machine_id'] ? 'MACHINE' : 'MANUAL',
                            'machine_id' => $step['machine_id'],
                            'qty' => $step['qty_in'] ?? $qty,
                            'waste_qty' => $step['qty_waste'] ?? null,
                            'waste_uom' => $step['qty_waste'] !== null ? $step['uom'] : null,
                            'tolerance_qty' => $step['qty_tolerance'] ?? null,
                            'tolerance_uom' => $step['qty_tolerance'] !== null ? $step['uom'] : null,
                            'task_status' => 'PENDING',
                            'rate_band_value' => $step['target_qty_per_hour'] ?? null,
                            'created_by' => 'system',
                        ]);
                    }
                } else {
                    // Belum ada BOP dari ProDev -- pakai routing snapshot Inquiry apa adanya.
                    foreach ($part->processes->sortBy('step_seq') as $routingStep) {
                        $targetRate = $this->lookupDefaultMachineRate(
                            $inquiry->product_id, $wo->part_code, $routingStep->process_id, $routingStep->machine_id,
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
                }

                foreach ($spec['bom'] ?? [] as $i => $b) {
                    if (($b['name'] ?? null) === 'None') {
                        continue;
                    }

                    PrdWoMaterial::create([
                        'wo_id' => $wo->wo_id,
                        'seq_no' => ($i + 1) * 10,
                        'material_id' => $b['material_id'],
                        'material_role' => $b['role'],
                        'material_name' => $b['name'],
                        // Baris yang luas pakainya belum bisa dihitung (mis. foil,
                        // varnish UV) tetap dibuat dgn qty 0 supaya PPIC melengkapi.
                        'qty_required' => $b['qty_need'] ?? 0,
                        'qty_standar' => $b['qty_need'] ?? 0,
                        'uom' => $b['uom'],
                        'material_needed_by' => $delivery->ship_date,
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
