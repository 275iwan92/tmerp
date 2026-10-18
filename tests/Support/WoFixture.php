<?php

/*
 * REMARK
 * Tujuan       : Fixture mandiri untuk tes WO / Plan Delivery / Worklist: tiap tes membangun Inquiry, CO, Plan Delivery dan WO sendiri, tanpa menumpang data dummy.
 * Sumber hulu  : Master asli yang dibaca saja (satu produk bermaster card dari vw_master_card, mesin OFF-02, proses Cetak/Laminasi/Plong, scrap type).
 * Input        : seedChain() untuk Inquiry + CO + Plan Delivery, seedWo() untuk WO langsung lengkap operasi dan BOM.
 * Output       : Baris bertanda IQWOFX-/COWOFX-/WOFX-/part WOFX di tm2sdi dan tm2prd; purgeWoFixtures() menghapus semuanya (anak dulu).
 * Dipakai oleh : ProductionWoGenerationTest, ProductionWoDetailRebuildTest, ProductionWorklistWoTest, CoListExpandRowTest.
 * Status       : Aktif. Menggantikan fixture lama CO2600001 / WO2600225-262 yang dihapus aturan B.17 (2026-10-18).
 */

namespace Tests\Support;

use App\Models\Master\ProdMachine;
use App\Models\Master\ProdPartProcessMachineRate;
use App\Models\Master\ProdProcess;
use App\Models\Prd\PrdWo;
use Illuminate\Support\Facades\DB;

trait WoFixture
{
    private const FX_INQ = 'IQWOFX-';

    private const FX_CO = 'COWOFX-';

    private const FX_WO = 'WOFX-';

    private const FX_PART = 'WOFX';

    protected function setUpWoFixture(): void
    {
        $this->purgeWoFixtures();
    }

    protected function tearDownWoFixture(): void
    {
        $this->purgeWoFixtures();
    }

    // Produk asli satu bagian yang sudah punya baris master card.
    protected function fxProductId(): int
    {
        return (int) DB::connection('tm2mst')->table('vw_master_card')
            ->selectRaw('id_product, count(*) n')->groupBy('id_product')->having('n', 1)->orderBy('id_product')->value('id_product');
    }

    protected function fxMachineId(): int
    {
        return (int) ProdMachine::where('machine_code', 'OFF-02')->value('machine_id');
    }

    /**
     * Inquiry (1 bagian, routing Cetak/Laminasi/Plong, 1 bahan) + CO CONFIRMED + item + 1 Plan Delivery PLANNED.
     * Rate Cetak = 6000/jam untuk mesin OFF-02 pada produk dan bagian fixture ini.
     *
     * @return array{inquiry_id: int, part_id: int, order_id: int, item_id: int, delivery_id: int, co_no: string, product_id: int}
     */
    protected function seedChain(string $tag, int $shipQty = 150): array
    {
        $sdi = DB::connection('tm2sdi');
        $now = now();
        $stamp = ['created_by' => 'test', 'created_at' => $now, 'updated_at' => $now];
        $productId = $this->fxProductId();
        $machineId = $this->fxMachineId();

        $inquiryId = $sdi->table('iqp_inquiry')->insertGetId([
            'inquiry_no' => self::FX_INQ.$tag, 'inquiry_date' => $now->toDateString(), 'is_prospect' => 1,
            'prospect_name' => 'Prospek Test WO', 'product_draft_name' => 'Box Test WO', 'inquiry_type' => 'NEW',
            'product_id' => $productId, 'sub_state' => 'CONVERTED',
        ] + $stamp);

        $partId = $sdi->table('iqp_part')->insertGetId([
            'inquiry_id' => $inquiryId, 'part_seq' => 1, 'part_code' => self::FX_PART, 'part_name' => 'Body Test WO', 'qty_per_sku' => 1,
        ] + $stamp);

        $sdi->table('iqp_part_material')->insert([
            'part_id' => $partId, 'layer_seq' => 1, 'rawmat_name' => 'DUPLEX TEST WO', 'gram_weight' => 310,
        ] + $stamp);

        foreach ([['Cetak', 10, $machineId], ['Laminasi', 20, null], ['Plong', 30, null]] as [$name, $seq, $machine]) {
            $sdi->table('iqp_part_process')->insert([
                'part_id' => $partId, 'branch_code' => 'MAIN', 'step_seq' => $seq, 'flow_type' => 'LINEAR', 'qty_factor' => 1,
                'process_id' => ProdProcess::where('process_name', $name)->value('id_process'), 'process_name' => $name, 'machine_id' => $machine,
            ] + $stamp);
        }

        ProdPartProcessMachineRate::updateOrCreate(
            ['product_id' => $productId, 'part_code' => self::FX_PART, 'process_id' => ProdProcess::where('process_name', 'Cetak')->value('id_process'), 'machine_id' => $machineId],
            ['activate' => true, 'created_by' => 'test', 'updated_by' => 'test', 'target_qty_per_hour' => 6000],
        );

        $orderId = $sdi->table('co_order')->insertGetId([
            'co_no' => self::FX_CO.$tag, 'co_date' => $now->toDateString(), 'doc_status' => 'CONFIRMED',
        ] + $stamp);
        $itemId = $sdi->table('co_order_item')->insertGetId([
            'order_id' => $orderId, 'line_seq' => 1, 'line_kind' => 'PRODUCT', 'inquiry_id' => $inquiryId, 'product_id' => $productId,
            'description' => 'Box Test WO', 'qty' => 500, 'unit_price' => 1000, 'subtotal' => 500000,
        ] + $stamp);
        $deliveryId = $sdi->table('co_order_delivery')->insertGetId([
            'order_id' => $orderId, 'item_id' => $itemId, 'shipment_type' => 'PARTIAL',
            'ship_date' => $now->copy()->addDays(30)->toDateString(), 'ship_qty' => $shipQty, 'status' => 'PLANNED',
        ] + $stamp);

        return ['inquiry_id' => (int) $inquiryId, 'part_id' => (int) $partId, 'order_id' => (int) $orderId, 'item_id' => (int) $itemId,
            'delivery_id' => (int) $deliveryId, 'co_no' => self::FX_CO.$tag, 'product_id' => $productId];
    }

    /**
     * WO langsung (DRAFT, NORMAL) di atas chain sendiri; $ops = [[process_name, task_status, qty]] pada mesin OFF-02.
     * Operasi pertama diberi target_start supaya tes jadwal punya nilai awal.
     */
    protected function seedWo(string $tag, array $ops = [['Cetak', 'PENDING', 800]]): PrdWo
    {
        $chain = $this->seedChain($tag);
        $now = now();
        $wo = PrdWo::create([
            'wo_no' => self::FX_WO.$tag, 'wo_type' => 'MTO', 'source_type' => 'CO_CONFIRMED', 'source_co_id' => $chain['order_id'],
            'source_delivery_id' => $chain['delivery_id'], 'source_iqp_part_id' => $chain['part_id'], 'product_id' => $chain['product_id'],
            'part_code' => self::FX_PART, 'qty' => 800, 'due_date' => $now->copy()->addDays(30), 'wo_status' => 'DRAFT',
            'schedule_layer' => 'SIMULATED', 'created_by' => 'test',
        ]);

        foreach ($ops as $i => [$name, $status, $qty]) {
            $wo->operations()->create([
                'seq_no' => ($i + 1) * 10, 'process_name' => $name, 'process_id' => ProdProcess::where('process_name', $name)->value('id_process'),
                'resource_type' => 'MACHINE', 'machine_id' => $this->fxMachineId(), 'qty' => $qty, 'task_status' => $status,
                'target_start' => $i === 0 ? $now->copy()->addDays(7)->setTime(7, 0) : null, 'created_by' => 'test',
            ]);
        }

        return $wo->fresh();
    }

    /** Hapus semua fixture dan turunannya (WO dari generate ikut), anak dulu baru induk. */
    protected function purgeWoFixtures(): void
    {
        $sdi = DB::connection('tm2sdi');
        $prd = DB::connection('tm2prd');

        $orderIds = $sdi->table('co_order')->where('co_no', 'like', self::FX_CO.'%')->pluck('id_order');
        $deliveryIds = $sdi->table('co_order_delivery')->whereIn('order_id', $orderIds)->pluck('id_delivery');
        $woIds = $prd->table('prd_wo')->where(fn ($q) => $q->where('wo_no', 'like', self::FX_WO.'%')
            ->orWhereIn('source_delivery_id', $deliveryIds)->orWhereIn('source_co_id', $orderIds))->pluck('wo_id');
        $opIds = $prd->table('prd_wo_operation')->whereIn('wo_id', $woIds)->pluck('operation_id');

        // Membuka WO membuat booking bahan per CO (header, baris, alokasi): hapus yang menunjuk fixture.
        $inv = DB::connection('tm2inv');
        $inquiryIds = $sdi->table('iqp_inquiry')->where('inquiry_no', 'like', self::FX_INQ.'%')->pluck('id_inquiry');
        $bookings = $inv->table('rm_booking')->where(fn ($q) => $q->whereIn('co_id', $orderIds)->orWhereIn('inquiry_id', $inquiryIds)
            ->orWhereIn('wo_id', $woIds)->orWhere('notes', 'like', '%'.self::FX_CO.'%'))->pluck('booking_id');
        $lines = $inv->table('rm_booking_d')->whereIn('booking_id', $bookings)->pluck('booking_detail_id');
        $inv->table('rm_booking_alloc')->whereIn('booking_detail_id', $lines)->delete();
        $inv->table('rm_booking_d')->whereIn('booking_id', $bookings)->delete();
        $inv->table('rm_booking')->whereIn('booking_id', $bookings)->delete();

        $eventIds = $prd->table('prd_breakdown_event')->whereIn('operation_id', $opIds)->pluck('event_id');
        $prd->table('prd_breakdown_event')->whereIn('event_id', $eventIds)->delete();
        foreach (['prd_crew_assignment', 'prd_wo_operation_machine', 'prd_operation_time_log'] as $t) {
            $prd->table($t)->whereIn('operation_id', $opIds)->delete();
        }
        $outputIds = $prd->table('prd_wo_operation_output')->whereIn('operation_id', $opIds)->pluck('output_id');
        $prd->table('prd_wo_operation_output_defect')->whereIn('output_id', $outputIds)->delete();
        $prd->table('prd_wo_operation_output')->whereIn('output_id', $outputIds)->delete();
        $prd->table('prd_wo_material_substitution')->whereIn('wo_id', $woIds)->delete();
        $prd->table('prd_wo_material')->whereIn('wo_id', $woIds)->delete();
        $prd->table('prd_wo_operation')->whereIn('wo_id', $woIds)->delete();
        $prd->table('prd_wo')->whereIn('wo_id', $woIds)->delete();

        $sdi->table('co_order_external_cost')->whereIn('order_id', $orderIds)->delete();
        $sdi->table('co_order_delivery')->whereIn('order_id', $orderIds)->delete();
        $sdi->table('co_order_item')->whereIn('order_id', $orderIds)->delete();
        $sdi->table('co_order')->whereIn('id_order', $orderIds)->delete();

        $partIds = $sdi->table('iqp_part')->whereIn('inquiry_id', $inquiryIds)->pluck('id_part');
        foreach (['iqp_part_material', 'iqp_part_process'] as $t) {
            $sdi->table($t)->whereIn('part_id', $partIds)->delete();
        }
        $sdi->table('iqp_part')->whereIn('id_part', $partIds)->delete();
        $sdi->table('iqp_inquiry')->whereIn('id_inquiry', $inquiryIds)->delete();

        DB::connection('tm2mst')->table('prod_part_process_machine_rate')->where('part_code', self::FX_PART)->delete();
    }
}
