<?php

namespace Tests\Feature\Concerns;

use App\Models\Inventory\InvTransfer;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\WhTask;
use App\Models\Master\MatRaw;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Master\WhZone;
use App\Services\Inventory\StockTransferService;
use Illuminate\Support\Facades\DB;

// Gedung uji kecil untuk uji Inventory & Warehouse v2.01 (Transit, hitung stok, subkon, titipan customer). Semua baris uji bertanda $by
// (created_by / create_by) supaya cleanFixture() bisa membersihkan semuanya; cur_stock bahan yang dipakai dikembalikan.
// Pemakai: const BY di kelas uji, panggil cleanFixture() di setUp dan tearDown, lalu verifikasi ke database.
trait BuildsTestWarehouse
{
    /** @var array<int, mixed> material_id => cur_stock awal */
    private array $curStock = [];

    protected function makeWarehouse(string $code): WhWarehouse
    {
        return WhWarehouse::create(['warehouse_code' => $code, 'warehouse_name' => 'Test '.$code, 'warehouse_type' => 'MIX', 'activate' => true, 'length_m' => 30, 'width_m' => 20,
            'floor_count' => 1, 'shape' => 'RECT', 'created_by' => self::BY, 'updated_by' => self::BY]);
    }

    protected function makeZone(WhWarehouse $wh, string $code, string $type, array $extra = []): WhZone
    {
        return WhZone::create(array_replace(['warehouse_id' => $wh->id_warehouse, 'floor_no' => 1, 'zone_code' => $code, 'zone_name' => 'Test '.$code, 'zone_type' => $type, 'storage_mode' => 'AREA',
            'stock_class' => 'RM', 'map_x' => 0, 'map_y' => 0, 'map_w' => 5, 'map_h' => 5, 'created_by' => self::BY, 'updated_by' => self::BY], $extra));
    }

    protected function makeBin(WhZone $zone, string $code, array $extra = []): WhLoc
    {
        return WhLoc::create(array_replace(['warehouse_id' => $zone->warehouse_id, 'zone_id' => $zone->id_zone, 'location_code' => $code, 'row_code' => $code, 'col_code' => '01', 'stack_code' => '1',
            'is_usable' => true, 'capacity_kg' => 100000, 'activate' => true, 'created_by' => self::BY, 'updated_by' => self::BY], $extra));
    }

    /** @return list<MatRaw> bahan yang belum punya kartu stok (hasilnya tidak bergantung pada data dummy lain) */
    protected function freeMaterials(int $n): array
    {
        $found = MatRaw::withoutGlobalScopes()->where('activate', true)->whereNotIn('material_id', RmStockcard::query()->whereNotNull('material_id')->distinct()->pluck('material_id'))
            ->orderBy('material_id')->limit($n)->get()->all();
        foreach ($found as $m) {
            $this->curStock[$m->material_id] = $m->cur_stock;
        }

        return $found;
    }

    // Saldo awal (BB) bahan di satu bin, harga 10 per satuan.
    protected function seedStock(MatRaw $m, WhLoc $bin, float $qty): void
    {
        (new RmStockcard)->forceFill(['doc_date' => now()->subDay(), 'material_id' => $m->material_id, 'doc_type' => 'BB', 'location_id' => $bin->location_id, 'warehouse_id' => $bin->warehouse_id,
            'qty' => $qty, 'uom_code' => 'KG', 'reference_name' => 'test:v201:'.$m->material_id.':'.$bin->location_id, 'reference_detail_id' => $bin->location_id, 'qty_bb' => 0, 'qty_in' => $qty,
            'qty_out' => 0, 'qty_adj' => 0, 'qty_eb' => $qty, 'avg_unitprice' => 10, 'value_eb' => $qty * 10, 'create_by' => self::BY, 'update_by' => self::BY])->save();
    }

    protected function balance(WhLoc $bin, MatRaw $m): float
    {
        return (new StockTransferService)->balance('RM', (int) $m->material_id, null, (int) $bin->location_id);
    }

    // Dokumen Stock Transfer RM satu baris (Draft). $mode DIRECT atau TWO_STEP.
    protected function draftTransfer(WhLoc $from, WhLoc $to, MatRaw $m, float $qty, string $mode = 'DIRECT'): InvTransfer
    {
        $doc = InvTransfer::create(['transfer_date' => now()->toDateString(), 'from_warehouse_id' => $from->warehouse_id, 'to_warehouse_id' => $to->warehouse_id, 'reason' => 'OTHER',
            'transit_mode' => $mode, 'created_by' => self::BY, 'updated_by' => self::BY]);
        $doc->lines()->create(['line_no' => 1, 'stock_class' => 'RM', 'item_id' => $m->material_id, 'item_code' => $m->material_code, 'item_name' => $m->material_name,
            'from_location_id' => $from->location_id, 'to_location_id' => $to->location_id, 'qty' => $qty, 'uom_code' => 'KG', 'created_by' => self::BY, 'updated_by' => self::BY]);

        return $doc->refresh();
    }

    protected function cleanFixture(): void
    {
        $inv = DB::connection('tm2inv');
        // Uji yang memanggil route (actingAs admin) menulis tugas / dokumen / kartu stok atas nama admin, bukan BY; ikat lewat bin uji supaya ikut bersih.
        $binIds = WhLoc::query()->where('created_by', self::BY)->pluck('location_id')->all() ?: [0];
        $taskNos = WhTask::query()->whereIn('from_location_id', $binIds)->pluck('task_no')->all() ?: [''];
        $issueIds = $inv->table('inv_subcon_issue')->where('created_by', self::BY)->pluck('id_subcon_issue')->all();
        $inv->table('inv_subcon_issue_line')->whereIn('issue_id', $issueIds ?: [0])->delete();
        $inv->table('inv_subcon_issue')->whereIn('id_subcon_issue', $issueIds ?: [0])->delete();
        $palletIds = $inv->table('inv_pallet')->where('created_by', self::BY)->pluck('id_pallet')->all();
        WhTask::query()->where('created_by', self::BY)->orWhereIn('pallet_id', $palletIds ?: [0])->orWhereIn('from_location_id', $binIds)->delete();
        $inv->table('inv_pallet_log')->whereIn('pallet_id', $palletIds ?: [0])->delete();
        $inv->table('inv_pallet')->whereIn('id_pallet', $palletIds ?: [0])->delete();
        $transferIds = $inv->table('inv_transfer')->where('created_by', self::BY)->pluck('id_transfer')->all();
        $inv->table('inv_transfer_line')->whereIn('transfer_id', $transferIds ?: [0])->delete();
        $inv->table('inv_transfer')->whereIn('id_transfer', $transferIds ?: [0])->delete();
        $docIds = $inv->table('rm_stock')->where('create_by', self::BY)->orWhere(fn ($q) => $q->where('ref_doc_type', 'COUNT_TASK')->whereIn('ref_doc_no', $taskNos))->pluck('doc_id')->all();
        $inv->table('rm_stock_d')->whereIn('doc_id', $docIds ?: [0])->delete();
        $inv->table('rm_stockcard')->where('create_by', self::BY)->orWhereIn('location_id', $binIds)->delete();
        $inv->table('rm_stock')->whereIn('doc_id', $docIds ?: [0])->delete();
        foreach ($this->curStock as $id => $value) {
            DB::connection('tm2mst')->table('mat_raw')->where('material_id', $id)->update(['cur_stock' => $value]);
        }
        $acc = DB::connection('tm2acc');
        $vouchers = $acc->table('fin_voucher')->where('created_by', self::BY)->pluck('voucher_id')->all();
        $acc->table('fin_voucher_line')->whereIn('voucher_id', $vouchers ?: [0])->delete();
        $acc->table('fin_voucher')->whereIn('voucher_id', $vouchers ?: [0])->delete();
        $pur = DB::connection('tm2pur');
        $pvIds = $pur->table('pv_receipt')->where('created_by', self::BY)->pluck('pv_id')->all();
        $pur->table('pv_line')->whereIn('pv_id', $pvIds ?: [0])->delete();
        $pur->table('pv_receipt')->whereIn('pv_id', $pvIds ?: [0])->delete();
        $poIds = $pur->table('po_order')->where('created_by', self::BY)->pluck('po_id')->all();
        $pur->table('po_line')->whereIn('po_id', $poIds ?: [0])->delete();
        $pur->table('po_order')->whereIn('po_id', $poIds ?: [0])->delete();
        // Juga bin / area / gudang virtual yang kebetulan pertama kali dibuat oleh uji ini (VirtualLocationService); dibuat ulang sesuai kebutuhan.
        $ids = WhWarehouse::query()->where('created_by', self::BY)->pluck('id_warehouse')->all();
        WhLoc::query()->where(fn ($q) => $q->whereIn('warehouse_id', $ids ?: [0])->orWhere('created_by', self::BY))->delete();
        WhZone::query()->where(fn ($q) => $q->whereIn('warehouse_id', $ids ?: [0])->orWhere('created_by', self::BY))->delete();
        WhWarehouse::query()->whereIn('id_warehouse', $ids ?: [0])->delete();
    }

    // Tidak ada baris uji yang tersisa di tabel mana pun (dipanggil di tearDown sesudah cleanFixture).
    protected function assertFixtureClean(): void
    {
        $count = fn (string $conn, string $table, string $col) => DB::connection($conn)->table($table)->where($col, self::BY)->count();
        foreach ([['tm2inv', 'inv_transfer', 'created_by'], ['tm2inv', 'inv_subcon_issue', 'created_by'], ['tm2inv', 'wh_task', 'created_by'], ['tm2inv', 'rm_stock', 'create_by'],
            ['tm2inv', 'rm_stockcard', 'create_by'], ['tm2pur', 'pv_receipt', 'created_by'], ['tm2pur', 'po_order', 'created_by'], ['tm2acc', 'fin_voucher', 'created_by'],
            ['tm2mst', 'wh_loc', 'created_by'], ['tm2mst', 'wh_zone', 'created_by'], ['tm2mst', 'wh_warehouse', 'created_by']] as [$conn, $table, $col]) {
            $this->assertSame(0, $count($conn, $table, $col), "Leftover test rows in {$conn}.{$table}");
        }
    }
}
