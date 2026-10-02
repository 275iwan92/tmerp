<?php

namespace Database\Seeders;

use App\Models\Acc\FinVoucher;
use App\Models\Acc\FinVoucherLine;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\RmStockD;
use App\Models\Master\FlowLog;
use App\Models\Master\MatRaw;
use App\Models\Master\OrgDepartment;
use App\Models\Master\WhLoc;
use App\Models\Pur\PvLine;
use App\Models\Pur\PvReceipt;
use App\Models\Sys\SecUser;
use App\Services\Flow\FlowEngineService;
use App\Services\Inventory\StockDocumentService;
use App\Services\Inventory\StockPostingService;
use App\Services\Inventory\StockTransferService;
use App\Services\Pur\PvBuilderService;
use App\Support\V2\Data\PurchaseReceive;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

// Dummy untuk laporan stok semua kelas (user 2026-10-03: "don't forget data dummy on mockup.test"): enam posisi stok baru
// (bahan yang sudah ada, bin yang masih kosong di gedung A10 / A5 / A6 buatan UatWarehouseSeeder) dengan riwayat empat bulan
// yang diposting BERURUTAN TANGGAL lewat aturan aplikasi (StockDocumentService + StockPostingService) -- Stock In, Stock Out,
// stock opname dan penyesuaian yang sudah Posted (selisih lebih / kurang di bulan berbeda), satu opname menunggu approval, satu
// penyesuaian masih Draft, satu bin diblokir yang berisi stok, dan penerimaan barang yang menunggu QC / ditolak sebagian.
// Laporan yang terisi: Stock Card, Stock Recapitulation, Stock Summary, Stock Opname, Stock Loss & Gain, Stock in Inspection.
// Hanya ddt*; diulang = dummy ini dihapus lalu dibuat lagi. Jalankan SESUDAH UatWarehouseSeeder:
//   php artisan db:seed --class=UatStockReportSeeder --env=mockup --force
class UatStockReportSeeder extends Seeder
{
    private const BY = 'uat_stock_reports';

    private const REF = 'tm2inv.rm_stock_d';

    // kunci => [kode bahan, kode bin]. Bin-bin ini kosong di UatWarehouseSeeder, jadi kartu stok tiap posisi dimulai dari nol.
    private const POSITIONS = [
        'HVS' => ['P4454', 'A5-PPR-L04-01'], 'TRIPLEX' => ['PZ05893', 'A5-PPR-L04-02'], 'IVORY' => ['PYJ31', 'A5-PPR-L05-01'],
        'CYAN' => ['IZ06028', 'A10-INK-R03-01-1'], 'GLUE' => ['GZ00726', 'A10-INK-R03-02-1'], 'PLATE' => ['TZ04469', 'A6-STR-L02-01'],
    ];

    // Bin yang diblokir sesudah terisi: stoknya tampil di Stock in Inspection sebagai "Blocked bin".
    private const BLOCKED = ['A5-PPR-L05-01', 'Roof leak above this lane: stock on hold until checked'];

    // [hari lalu, jenis, catatan, [posisi => angka]]. Diurutkan dan diposting dari yang paling lama.
    //   IN  : angka = [qty, harga satuan]      OUT : angka = qty keluar
    //   OP / AD (Posted), OP_WAIT (menunggu approval), AD_DRAFT : angka = selisih fisik - sistem (minus = kurang)
    private const EVENTS = [
        [100, 'IN', 'Opening stock for the stock reports (dummy).', ['HVS' => [20000, 600], 'TRIPLEX' => [6000, 2900], 'IVORY' => [8000, 3100]]],
        [100, 'IN', 'Opening stock for the stock reports (dummy).', ['CYAN' => [80, 135000], 'GLUE' => [200, 100000]]],
        [100, 'IN', 'Opening stock for the stock reports (dummy).', ['PLATE' => [300, 65000]]],
        [86, 'OUT', 'Paper for the July print run.', ['HVS' => 4000, 'IVORY' => 1500]],
        [84, 'OUT', 'Ink and glue for the July print run.', ['CYAN' => 12, 'GLUE' => 30]],
        [76, 'OP', 'Monthly physical count, July.', ['HVS' => -150, 'TRIPLEX' => 40, 'IVORY' => 0]],
        [75, 'OP', 'Monthly physical count, July.', ['CYAN' => -1.5, 'GLUE' => 2]],
        [55, 'OUT', 'Paper for the August print run.', ['HVS' => 3000, 'TRIPLEX' => 1200]],
        [48, 'OP', 'Monthly physical count, August.', ['HVS' => 80, 'TRIPLEX' => -25, 'IVORY' => -60]],
        [44, 'AD', 'Plates bent while being moved.', ['PLATE' => -12]],
        [21, 'IN', 'Replenishment.', ['HVS' => [10000, 620]]],
        [20, 'OUT', 'Ink and glue for the September print run.', ['CYAN' => 10, 'GLUE' => 40]],
        [13, 'OP', 'Monthly physical count, September.', ['CYAN' => 0.5, 'GLUE' => -3]],
        [12, 'OP', 'Monthly physical count, September.', ['PLATE' => 2]],
        [2, 'OP', 'Count before the bin was blocked.', ['IVORY' => 25]],
        [1, 'OP_WAIT', 'Monthly physical count, October: waiting for approval.', ['HVS' => -200, 'TRIPLEX' => 0]],
        [1, 'AD_DRAFT', 'Glue tin found leaking: not submitted yet.', ['GLUE' => -5]],
    ];

    private const REASONS = ['OP' => 'COUNT_ERROR', 'AD' => 'DAMAGED', 'OP_WAIT' => 'COUNT_ERROR', 'AD_DRAFT' => 'DAMAGED'];

    public function run(): void
    {
        foreach (['tm2mst', 'tm2inv', 'tm2pur', 'tm2acc'] as $connection) {
            if (! str_starts_with(DB::connection($connection)->getDatabaseName(), 'ddt')) {
                throw new RuntimeException('UatStockReportSeeder only runs on the dummy database (ddt*): add --env=mockup.');
            }
        }
        // Aturan penerimaan barang dan approval membaca user yang sedang login.
        Auth::setUser(SecUser::query()->where('is_admin', 1)->orderBy('id_user')->firstOrFail());
        $this->clean();
        $positions = $this->positions();
        $this->documents($positions);
        $this->blockBin();
        $this->receipts();
    }

    // Hapus dummy seeder ini: jurnal pemakaian, log approval, kartu stok (+ cur_stock dikembalikan), dokumen, penerimaan, blokir bin.
    private function clean(): void
    {
        $docIds = RmStock::query()->where('create_by', self::BY)->pluck('doc_id')->all();
        $voucherIds = FinVoucher::query()->where('source_type', 'STOCK_OUT_RM')->whereIn('source_id', $docIds ?: [0])->pluck('voucher_id')->all();
        FinVoucherLine::query()->whereIn('voucher_id', $voucherIds ?: [0])->delete();
        FinVoucher::query()->whereIn('voucher_id', $voucherIds ?: [0])->delete();
        FlowLog::query()->whereIn('module_code', ['OP', 'AD'])->whereIn('doc_id', $docIds ?: [0])->delete();

        $lineIds = RmStockD::query()->whereIn('doc_id', $docIds ?: [0])->pluck('doc_detail_id')->all();
        $cards = RmStockcard::query()->where('reference_name', self::REF)->whereIn('reference_detail_id', $lineIds ?: [0])->get(['doc_id', 'material_id', 'qty']);
        foreach ($cards->groupBy('material_id') as $materialId => $rows) {
            MatRaw::withoutGlobalScopes()->whereKey($materialId)->update(['cur_stock' => DB::raw('COALESCE(cur_stock, 0) - ('.(float) $rows->sum('qty').')')]);
        }
        RmStockcard::query()->whereIn('doc_id', $cards->pluck('doc_id')->all() ?: [0])->delete();
        RmStockD::query()->whereIn('doc_id', $docIds ?: [0])->delete();
        RmStock::query()->whereIn('doc_id', $docIds ?: [0])->delete();

        $pvIds = PvReceipt::query()->where('created_by', self::BY)->pluck('pv_id')->all();
        PvLine::query()->whereIn('pv_id', $pvIds ?: [0])->delete();
        PvReceipt::query()->whereIn('pv_id', $pvIds ?: [0])->delete();

        WhLoc::query()->where('location_code', self::BLOCKED[0])->where('updated_by', self::BY)->update(['is_usable' => true, 'block_reason' => null]);
    }

    // kunci => ['material' => MatRaw, 'bin' => WhLoc]; posisi harus masih kosong (kartu stoknya dimulai seeder ini).
    private function positions(): array
    {
        $materials = MatRaw::withoutGlobalScopes()->whereIn('material_code', array_column(self::POSITIONS, 0))->get()->keyBy('material_code');
        $bins = WhLoc::query()->whereIn('location_code', array_column(self::POSITIONS, 1))->get()->keyBy('location_code');
        $out = [];
        foreach (self::POSITIONS as $key => [$code, $binCode]) {
            $material = $materials[$code] ?? throw new RuntimeException("Material {$code} not found.");
            $bin = $bins[$binCode] ?? throw new RuntimeException("Bin {$binCode} not found: run UatWarehouseSeeder first.");
            if (RmStockcard::query()->where('material_id', $material->material_id)->where('location_id', $bin->location_id)->exists()) {
                throw new RuntimeException("Bin {$binCode} already holds stock card rows of {$code} that this seeder did not make.");
            }
            $out[$key] = ['material' => $material, 'bin' => $bin];
        }

        return $out;
    }

    private function documents(array $positions): void
    {
        $docs = new StockDocumentService;
        $post = new StockPostingService;
        $transfer = new StockTransferService;
        $dept = OrgDepartment::query()->orderBy('department_id')->value('department_id');
        $events = self::EVENTS;
        usort($events, fn ($a, $b) => $b[0] <=> $a[0]);

        foreach ($events as [$days, $kind, $note, $rows]) {
            $date = now()->subDays($days)->startOfDay()->addHours(9);
            $warehouseId = $positions[array_key_first($rows)]['bin']->warehouse_id;
            $lines = [];
            foreach ($rows as $key => $figure) {
                ['material' => $m, 'bin' => $bin] = $positions[$key];
                $base = ['material_id' => $m->material_id, 'uom_code' => $m->uom_stock ?: 'PCS', 'location_id' => $bin->location_id, 'lot_no' => null];
                if ($kind === 'IN') {
                    $lines[] = $base + ['qty' => $figure[0], 'unit_price' => $figure[1], 'qty_unitprice' => $figure[0] * $figure[1], 'lot_no' => 'LOT-'.$date->format('ymd')];
                } elseif ($kind === 'OUT') {
                    $lines[] = $base + ['qty' => $figure, 'action_type' => 'ISSUE'];
                } else {
                    // Qty sistem dan harga = baris kartu terakhir posisi itu saat dokumen dibuat (sama dengan layar Stock Adjustment).
                    $system = $transfer->balance('RM', (int) $m->material_id, null, (int) $bin->location_id);
                    $price = (float) RmStockcard::query()->where('material_id', $m->material_id)->where('location_id', $bin->location_id)
                        ->orderByDesc('doc_date')->orderByDesc('doc_id')->value('avg_unitprice');
                    $lines[] = $base + ['qty' => $figure, 'qty_system' => $system, 'qty_physical' => $system + $figure, 'unit_price' => $price, 'action_type' => 'ADJUST'];
                }
            }

            $count = in_array($kind, ['OP', 'OP_WAIT'], true);
            $head = match (true) {
                $kind === 'IN' => ['doc_no' => $docs->nextDocNo('RM', 'IN'), 'doc_type' => 1, 'doc_cat' => 'STOCK'],
                $kind === 'OUT' => ['doc_no' => $docs->nextDocNo('RM', 'OUT'), 'doc_type' => -1, 'doc_cat' => 'STOCK', 'issued_to_dept' => $dept, 'process_code' => 'Offset printing'],
                default => ['doc_no' => $docs->nextDocNo('RM', $count ? 'OPN' : 'ADJ'), 'doc_type' => -1, 'doc_cat' => $count ? 'OP' : 'AD', 'reason_code' => self::REASONS[$kind],
                    'opn_officer' => $count ? 'MAHMUD SUPAAT' : null, 'opn_witness' => $count ? 'Accounting staff' : null,
                    'opn_start' => $count ? $date : null, 'opn_stop' => $count ? $date->copy()->addHours(3) : null, 'full_scope' => false],
            };
            $id = $docs->saveDraft('RM', null, $head + ['doc_date' => $date, 'ref_doc_type' => 'MANUAL', 'warehouse_id' => $warehouseId, 'notes' => $note], $lines, self::BY);
            DB::connection('tm2inv')->table('rm_stock')->where('doc_id', $id)->update(['data_origin' => 'DUMMY']);

            if ($kind === 'AD_DRAFT') {
                continue;
            }
            if ($kind === 'OP_WAIT') {
                // Sama dengan tombol Submit for Approval: status SUBMITTED + antrean approval (bila konfigurasinya aktif).
                RmStock::query()->whereKey($id)->update(['flow_status' => 'SUBMITTED', 'update_by' => self::BY]);
                $amount = collect($lines)->sum(fn ($l) => abs($l['qty'] * $l['unit_price']));
                rescue(fn () => app(FlowEngineService::class)->submit('OP', $id, 'SUBMITTED', $amount), null, false);

                continue;
            }
            try {
                $result = $post->postDocument('RM', $id, self::BY);
            } catch (Throwable $e) {
                // Kartu stok sudah tertulis; yang bisa gagal sesudahnya hanya jurnal pemakaian (mis. periode akuntansi ditutup).
                $result = ['posted' => (bool) RmStock::query()->whereKey($id)->value('stat_confirm'), 'error' => $e->getMessage()];
                $this->command?->warn("{$head['doc_no']}: stock posted, usage journal skipped ({$e->getMessage()}).");
            }
            if (! $result['posted']) {
                throw new RuntimeException("{$head['doc_no']}: ".$result['error']);
            }
        }
        DB::connection('tm2inv')->table('rm_stockcard')->where('create_by', self::BY)->update(['data_origin' => 'DUMMY']);
    }

    private function blockBin(): void
    {
        WhLoc::query()->where('location_code', self::BLOCKED[0])->update(['is_usable' => false, 'block_reason' => self::BLOCKED[1], 'updated_by' => self::BY]);
    }

    // Penerimaan barang lewat aturan Purchase Receive: satu yang seluruh barisnya menunggu QC, dan (bila ada supplier kedua
    // dengan PO terbuka) satu yang baris pertamanya sudah dinilai dengan sebagian ditolak. Tidak diposting: belum jadi stok.
    private function receipts(): void
    {
        $suppliers = PvBuilderService::supplierWorklist();
        if ($suppliers->isEmpty()) {
            $this->command?->warn('No supplier with an open purchase order: no goods receipt was made for Stock in Inspection.');

            return;
        }
        foreach ($suppliers->take(2)->values() as $n => $supplier) {
            $lineIds = PvBuilderService::openLinesForSupplier((int) $supplier->i_supplier_id)->pluck('po_line_id')->all();
            $pv = PvBuilderService::createFromLines((int) $supplier->i_supplier_id, 'A10', $lineIds);
            if ($n === 1 || $suppliers->count() === 1) {
                $line = $pv->lines()->orderBy('line_no')->first();
                $rejected = round((float) $line->i_qty_stock * 0.1, 2);
                if ($line && $rejected > 0 && ($suppliers->count() > 1 || $pv->lines()->count() > 1)) {
                    $c = PurchaseReceive::component($pv->pv_id);
                    $c->markQtyDone($line->pv_line_id);
                    $c->setQuality($line->pv_line_id, 'PARTIAL');
                    $c->updateQcQty($line->pv_line_id, 'c_qty_accepted', (float) $line->i_qty_stock - $rejected);
                    $c->updateQcQty($line->pv_line_id, 'c_qty_rejected', $rejected);
                    PvLine::query()->whereKey($line->pv_line_id)->update(['qc_note' => 'Laminate peeling on part of the delivery: to be returned.']);
                }
            }
            PvReceipt::query()->whereKey($pv->pv_id)->update(['created_by' => self::BY, 'i_notes' => 'Dummy for the Stock in Inspection report.']);
        }
    }
}
