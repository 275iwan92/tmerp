<?php

namespace Database\Seeders;

use App\Models\Inventory\FgStock;
use App\Models\Inventory\FgStockcard;
use App\Models\Inventory\FgStockD;
use App\Models\Inventory\InvLabelPrint;
use App\Models\Inventory\InvPallet;
use App\Models\Inventory\InvPalletLog;
use App\Models\Inventory\InvTransfer;
use App\Models\Inventory\InvTransferLine;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\RmStockD;
use App\Models\Master\MatRaw;
use App\Models\Master\ProdProduct;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Master\WhZone;
use App\Services\Inventory\BinGenerator;
use App\Services\Inventory\PalletService;
use App\Services\Inventory\StockDocumentService;
use App\Services\Inventory\StockPostingService;
use App\Services\Inventory\StockTransferService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Dummy Inventory & Warehouse (user 2026-10-02): tiga gedung nyata A10 / A5 / A6 (kotak, 2 lantai; ukuran = PERKIRAAN,
// "nanti di-adjust oleh officer"), area + bin per lantai, stok contoh yang masuk lewat Stock In sungguhan (kartu stok,
// harga rata-rata) dengan tanggal berbeda supaya ada bin terisi, penuh, lebih dari kapasitas, diblokir, dan DEAD STOCK,
// lalu Stock Transfer (tiga Confirmed, satu Draft). Putaran 2: tinggi tiap lantai + tinggi tumpuk area (kapasitas m3),
// palet berlabel (kode palet supplier, lot, kedaluwarsa: ada yang kedaluwarsa dan yang segera), posisi yang BELUM berlabel
// (label kedatangan) dan yang labelnya tidak cocok lagi (label ulang). Hanya ddt*; diulang = dummy ini dihapus lalu dibuat lagi.
//   php artisan db:seed --class=UatWarehouseSeeder --env=mockup --force
class UatWarehouseSeeder extends Seeder
{
    private const BY = 'uat_warehouse';

    // kode => [nama, panjang m, lebar m, lantai, [tinggi bersih lantai 1, lantai 2] m] -- semua PERKIRAAN.
    private const BUILDINGS = [
        'A10' => ['Warehouse A10', 30, 20, 2, [7, 3.5]],
        'A5' => ['Warehouse A5', 24, 16, 2, [6, 3.2]],
        'A6' => ['Warehouse A6', 20, 12, 2, [5, 3]],
    ];

    // Tinggi tumpuk per area (m): tumpukan palet kertas, rak, barang jadi; area lain = STACK_DEFAULT.
    private const STACK = ['PPR' => 1.6, 'PPR2' => 1.6, 'SHT' => 1.6, 'CSG' => 1.6, 'DSK' => 1.6, 'INK' => 2.4, 'TLG' => 2.0, 'FG' => 1.8, 'STR' => 1.8];

    private const STACK_DEFAULT = 1.5;

    // gedung => [[lantai, kode, nama, jenis, cara simpan, kelas stok, x, y, w, h, [lajur/rak, slot/kolom, tingkat], kapasitas kg, kapasitas pallet]]
    private const ZONES = [
        'A10' => [
            [1, 'REJ', 'Reject / Waste', 'REJECT', 'AREA', 'ANY', 0.5, 0.5, 6, 3.5, null, 1000, null],
            [1, 'QC', 'QC Hold', 'QC_HOLD', 'AREA', 'RM', 0.5, 5, 6, 4, null, 4000, 4],
            [1, 'RCV', 'Receiving', 'RECEIVING', 'AREA', 'ANY', 0.5, 10, 6, 9.5, null, 12000, 10],
            [1, 'PPR', 'Plano Paper', 'STORAGE', 'FLOOR', 'RM', 7.5, 0.5, 13.5, 12.5, [6, 6, 1], 3000, 4],
            [1, 'SHT', 'Sheet & Single Face', 'STORAGE', 'FLOOR', 'RM', 7.5, 14, 13.5, 5.5, [5, 2, 1], 2000, 3],
            [1, 'INK', 'Ink & Chemicals', 'STORAGE', 'RACK', 'RM', 22, 0.5, 7.5, 8, [4, 4, 3], 300, null],
            [1, 'STG', 'Production Staging', 'STAGING', 'AREA', 'ANY', 22, 9.5, 7.5, 4.5, null, 6000, 6],
            [1, 'SHP', 'Shipping', 'SHIPPING', 'AREA', 'FG', 22, 15, 7.5, 4.5, null, 8000, 8],
            [2, 'FG', 'Finished Goods', 'STORAGE', 'FLOOR', 'FG', 1, 1, 17, 11, [6, 4, 1], 1500, 2],
            [2, 'CSG', 'Customer Consignment', 'CONSIGN', 'FLOOR', 'RM', 19.5, 1, 9.5, 6, [3, 2, 1], 2000, 3],
            [2, 'TLG', 'Die & Plate Rack', 'TOOLING', 'RACK', 'ANY', 19.5, 8.5, 9.5, 6, [4, 2, 2], 200, null],
        ],
        'A5' => [
            [1, 'RCV', 'Receiving', 'RECEIVING', 'AREA', 'ANY', 0.5, 0.5, 5, 15, null, 10000, 8],
            [1, 'PPR', 'Plano Paper', 'STORAGE', 'FLOOR', 'RM', 6.5, 0.5, 17, 15, [6, 5, 1], 3000, 4],
            [2, 'PPR2', 'Paper Reserve', 'STORAGE', 'FLOOR', 'RM', 1, 1, 15, 10, [5, 4, 1], 3000, 4],
        ],
        'A6' => [
            [1, 'STR', 'General Storage', 'STORAGE', 'FLOOR', 'ANY', 1, 1, 12, 10, [4, 3, 1], 2000, 3],
            [2, 'DSK', 'Dead Stock Area', 'STORAGE', 'FLOOR', 'ANY', 1, 1, 10, 10, [3, 3, 1], 3000, 4],
        ],
    ];

    // [kode bahan, kode bin, qty, berapa hari lalu masuk, harga satuan]
    private const RM_STOCK = [
        ['PYJ31', 'A10-PPR-L01-01', 10000, 20, 3100], ['PYJ31', 'A10-PPR-L01-02', 11000, 20, 3100], ['PZ00083', 'A10-PPR-L01-03', 9000, 45, 1900],
        ['PZ05893', 'A10-PPR-L02-01', 12500, 10, 2900], ['PDD37037', 'A10-PPR-L02-02', 8000, 30, 2400], ['P4454', 'A10-PPR-L02-04', 20000, 60, 600],
        ['PZ06061', 'A10-PPR-L03-01', 15000, 275, 1300], ['PZ05649', 'A10-PPR-L03-02', 3000, 395, 9500], ['PZ00083', 'A10-PPR-L04-01', 6000, 5, 1900],
        ['PYJ31', 'A10-PPR-L05-03', 4000, 2, 3100],
        ['IOH12', 'A10-INK-R01-01-1', 60, 15, 150000], ['IZ06028', 'A10-INK-R01-01-2', 120, 25, 135000], ['IZ05964', 'A10-INK-R01-02-1', 40, 335, 120000],
        ['GZ00726', 'A10-INK-R02-01-1', 280, 7, 100000], ['TZ04469', 'A10-INK-R02-03-2', 200, 40, 65000],
        // Paling baru = kemarin: tidak ada baris kartu bertanggal hari ini, supaya penerimaan yang dicoba user hari ini selalu baris terakhir.
        ['PYJ31', 'A10-RCV', 5000, 1, 3150], ['PZ00083', 'A10-QC', 2500, 3, 1900],
        ['PYJ31', 'A5-PPR-L01-01', 10000, 35, 3050], ['PZ05893', 'A5-PPR-L01-02', 9000, 50, 2850], ['P4454', 'A5-PPR-L02-01', 30000, 70, 600],
        ['PDD37037', 'A5-PPR-L03-01', 6000, 300, 2300], ['PZ00083', 'A5-PPR-L03-02', 5000, 20, 1850], ['PZ00083', 'A5-PPR2-L01-01', 8000, 90, 1850],
        ['TZ04469', 'A6-STR-L01-01', 500, 15, 65000],
    ];

    // [kode product, kode bin, qty, berapa hari lalu masuk]
    private const FG_STOCK = [
        ['FB-DUPLEX-001', 'A10-FG-L01-01', 12000, 12], ['RSC-CORR-002', 'A10-FG-L01-02', 800, 30], ['LBL-STK-003', 'A10-FG-L02-01', 50000, 245],
    ];

    // [kode bahan, kode bin, [qty per palet], awalan kode palet supplier, kedaluwarsa = hari sesudah tanggal datang]
    // Posisi yang tidak disebut di sini sengaja belum berlabel (muncul di daftar "Arrival label").
    private const PALLETS = [
        ['PYJ31', 'A10-PPR-L01-01', [5000, 5000], 'IKPP', 365], ['PYJ31', 'A10-PPR-L01-02', [5500, 5500], 'IKPP', 365], ['PZ00083', 'A10-PPR-L01-03', [4500, 4500], 'SPM', 365],
        ['PZ05893', 'A10-PPR-L02-01', [6250, 6250], 'FSW', 365], ['PDD37037', 'A10-PPR-L02-02', [4000, 4000], 'PKR', 365], ['P4454', 'A10-PPR-L02-04', [10000, 10000], 'SPM', 365],
        // datang 275 hari lalu + 300 hari = kedaluwarsa 25 hari lagi (Expires soon)
        ['PZ06061', 'A10-PPR-L03-01', [7500, 7500], 'IKPP', 300],
        // datang 395 hari lalu + 365 hari = sudah kedaluwarsa; ikut pindah ke A6 lewat transfer kedua
        ['PZ05649', 'A10-PPR-L03-02', [3000], 'FSW', 365],
        ['PZ00083', 'A10-PPR-L04-01', [3000, 3000], 'SPM', 365],
        ['IOH12', 'A10-INK-R01-01-1', [60], 'DIC', 540], ['IZ06028', 'A10-INK-R01-01-2', [60, 60], 'TOYO', 365], ['IZ05964', 'A10-INK-R01-02-1', [40], 'TOYO', 360],
        ['GZ00726', 'A10-INK-R02-01-1', [140, 140], 'HNK', 180],
        // 4.000 pertama ikut pindah ke A10 lewat transfer pertama (palet tertua yang jumlahnya persis sama)
        ['PYJ31', 'A5-PPR-L01-01', [4000, 3000, 3000], 'IKPP', 365], ['PZ05893', 'A5-PPR-L01-02', [4500, 4500], 'FSW', 365], ['P4454', 'A5-PPR-L02-01', [15000, 15000], 'SPM', 365],
        ['PDD37037', 'A5-PPR-L03-01', [6000], 'PKR', 280],
    ];

    // [gedung asal, gedung tujuan, alasan, catatan, pembawa, confirm?, [[kode bahan, bin asal, bin tujuan, qty]]]
    private const TRANSFERS = [
        ['A5', 'A10', 'REPLENISH', 'Paper for next week print run.', 'MAHMUD SUPAAT', true, [['PYJ31', 'A5-PPR-L01-01', 'A10-PPR-L05-03', 4000]]],
        ['A10', 'A6', 'DEAD_STOCK', 'Dead stock moved aside to free a paper lane.', 'MAHMUD SUPAAT', true, [['PZ05649', 'A10-PPR-L03-02', 'A6-DSK-L01-01', 3000]]],
        // Sebagian isi satu palet: palet tidak ikut pindah -> bin asal minta label ulang, area staging minta label baru.
        ['A10', 'A10', 'TO_PRODUCTION', 'Board for the corrugated order: part of one pallet, to the staging area.', 'MAHMUD SUPAAT', true, [['P4454', 'A10-PPR-L02-04', 'A10-STG', 3000]]],
        ['A10', 'A10', 'TO_PRODUCTION', 'Duplex for the folding box order, to the staging area.', null, false, [['PZ00083', 'A10-PPR-L04-01', 'A10-STG', 2000]]],
    ];

    public function run(): void
    {
        if (! str_starts_with(DB::connection('tm2mst')->getDatabaseName(), 'ddt') || ! str_starts_with(DB::connection('tm2inv')->getDatabaseName(), 'ddt')) {
            throw new RuntimeException('UatWarehouseSeeder only runs on the dummy database (ddt*): add --env=mockup.');
        }
        $this->clean();
        $bins = $this->layout();
        $this->stock($bins);
        $this->pallets($bins);
        $this->transfers($bins);
    }

    // Hapus dummy seeder ini (urutan: transfer -> dokumen stok + kartunya -> bin -> area -> gedung) dan kembalikan cur_stock.
    private function clean(): void
    {
        $inv = DB::connection('tm2inv');
        $lineIds = InvTransferLine::query()->whereIn('transfer_id', InvTransfer::query()->where('created_by', self::BY)->pluck('id_transfer'))->pluck('id_transfer_line')->all();
        foreach (['rm_stockcard', 'sfg_stockcard', 'fg_stockcard'] as $card) {
            $inv->table($card)->where('reference_name', 'like', StockTransferService::REF.'%')->whereIn('reference_detail_id', $lineIds ?: [0])->delete();
        }
        InvTransfer::query()->where('created_by', self::BY)->delete();
        $palletIds = InvPallet::query()->where('created_by', self::BY)->pluck('id_pallet')->all();
        InvPalletLog::query()->whereIn('pallet_id', $palletIds ?: [0])->delete();
        InvPallet::query()->whereIn('id_pallet', $palletIds ?: [0])->delete();
        InvLabelPrint::query()->where('printed_by', self::BY)->delete();

        foreach ([[RmStock::class, RmStockD::class, RmStockcard::class, 'tm2inv.rm_stock_d', 'material_id', MatRaw::class],
            [FgStock::class, FgStockD::class, FgStockcard::class, 'tm2inv.fg_stock_d', 'product_id', ProdProduct::class]] as [$head, $line, $card, $ref, $itemKey, $master]) {
            $docIds = $head::query()->where('create_by', self::BY)->pluck('doc_id')->all();
            $lines = $line::query()->whereIn('doc_id', $docIds ?: [0])->get();
            $card::query()->where('reference_name', $ref)->whereIn('reference_detail_id', $lines->pluck('doc_detail_id')->all() ?: [0])->delete();
            foreach ($lines->groupBy($itemKey) as $itemId => $rows) {
                $master::withoutGlobalScopes()->whereKey($itemId)->update(['cur_stock' => DB::raw('COALESCE(cur_stock, 0) - ('.(float) $rows->sum('qty').')')]);
            }
            $line::query()->whereIn('doc_id', $docIds ?: [0])->delete();
            $head::query()->whereIn('doc_id', $docIds ?: [0])->delete();
        }
        $whIds = WhWarehouse::query()->where('created_by', self::BY)->pluck('id_warehouse')->all();
        WhLoc::query()->whereIn('warehouse_id', $whIds ?: [0])->delete();
        WhZone::query()->whereIn('warehouse_id', $whIds ?: [0])->delete();
        WhWarehouse::query()->whereIn('id_warehouse', $whIds ?: [0])->delete();
    }

    // Gedung, area, bin. Kembalian: kode bin => WhLoc.
    private function layout(): Collection
    {
        foreach (self::BUILDINGS as $code => [$name, $length, $width, $floors, $heights]) {
            if (WhWarehouse::query()->where('warehouse_code', $code)->exists()) {
                throw new RuntimeException("Warehouse {$code} already exists and was not made by this seeder.");
            }
            $wh = WhWarehouse::create(['warehouse_code' => $code, 'warehouse_name' => $name, 'warehouse_type' => 'MIX', 'activate' => true, 'length_m' => $length, 'width_m' => $width,
                'floor_count' => $floors, 'shape' => 'RECT', 'height_f1_m' => $heights[0] ?? null, 'height_f2_m' => $heights[1] ?? null, 'height_f3_m' => $heights[2] ?? null,
                'created_by' => self::BY, 'updated_by' => self::BY]);
            DB::connection('tm2mst')->table('wh_warehouse')->where('id_warehouse', $wh->id_warehouse)->update(['data_origin' => 'DUMMY']);
            foreach (self::ZONES[$code] as $i => [$floor, $zc, $zn, $type, $mode, $class, $x, $y, $w, $h, $gen, $kg, $pallet]) {
                $zone = WhZone::create(['warehouse_id' => $wh->id_warehouse, 'floor_no' => $floor, 'zone_code' => $zc, 'zone_name' => $zn, 'zone_type' => $type, 'storage_mode' => $mode,
                    'stock_class' => $class, 'map_x' => $x, 'map_y' => $y, 'map_w' => $w, 'map_h' => $h, 'stack_height_m' => self::STACK[$zc] ?? self::STACK_DEFAULT,
                    'sort_order' => ($i + 1) * 10, 'activate' => true,
                    'created_at' => now(), 'updated_at' => now(), 'created_by' => self::BY, 'updated_by' => self::BY]);
                BinGenerator::generate($zone->setRelation('warehouse', $wh), ['rows_from' => 1, 'rows_to' => $gen[0] ?? 1, 'cols_from' => 1, 'cols_to' => $gen[1] ?? 1,
                    'levels' => $gen[2] ?? 1, 'capacity_kg' => $kg, 'capacity_pallet' => $pallet], self::BY);
            }
        }
        // Satu bin diblokir (lantai diperbaiki): terlihat berarsir di peta dan tidak bisa jadi tujuan transfer.
        WhLoc::query()->where('location_code', 'A10-PPR-L06-06')->update(['is_usable' => false, 'block_reason' => 'Floor repair until 15-Oct-2026', 'updated_by' => self::BY]);
        $ids = WhWarehouse::query()->where('created_by', self::BY)->pluck('id_warehouse')->all();
        DB::connection('tm2mst')->table('wh_loc')->whereIn('warehouse_id', $ids)->update(['data_origin' => 'DUMMY']);

        return WhLoc::query()->whereIn('warehouse_id', $ids)->get()->keyBy('location_code');
    }

    // Stok masuk lewat dokumen Stock In sungguhan (satu dokumen per gedung per tanggal), diposting oleh StockPostingService.
    private function stock($bins): void
    {
        $docs = new StockDocumentService;
        $post = new StockPostingService;
        $materials = MatRaw::withoutGlobalScopes()->whereIn('material_code', array_unique(array_column(self::RM_STOCK, 0)))->get()->keyBy('material_code');
        $products = ProdProduct::withoutGlobalScopes()->whereIn('product_code', array_unique(array_column(self::FG_STOCK, 0)))->get()->keyBy('product_code');
        $plan = [];
        foreach (self::RM_STOCK as [$code, $bin, $qty, $days, $price]) {
            $m = $materials[$code] ?? throw new RuntimeException("Material {$code} not found.");
            $loc = $bins[$bin] ?? throw new RuntimeException("Bin {$bin} not found.");
            $plan['RM'][$loc->warehouse_id.'|'.$days][] = ['material_id' => $m->material_id, 'qty' => $qty, 'uom_code' => $m->uom_stock ?: 'PCS', 'unit_price' => $price,
                'qty_unitprice' => $qty * $price, 'lot_no' => 'LOT-'.now()->subDays($days)->format('ymd'), 'location_id' => $loc->location_id];
        }
        foreach (self::FG_STOCK as [$code, $bin, $qty, $days]) {
            $p = $products[$code] ?? throw new RuntimeException("Product {$code} not found.");
            $loc = $bins[$bin] ?? throw new RuntimeException("Bin {$bin} not found.");
            $plan['FG'][$loc->warehouse_id.'|'.$days][] = ['product_id' => $p->id_product, 'qty' => $qty, 'uom_code' => 'PCS', 'unit_price' => 0, 'lot_no' => null, 'location_id' => $loc->location_id];
        }
        foreach ($plan as $class => $groups) {
            // Dari yang paling lama supaya urutan kartu stok = urutan tanggal.
            uksort($groups, fn ($a, $b) => (int) explode('|', $b)[1] <=> (int) explode('|', $a)[1]);
            foreach ($groups as $key => $lines) {
                [$whId, $days] = explode('|', $key);
                $id = $docs->saveDraft($class, null, ['doc_no' => $docs->nextDocNo($class, 'IN'), 'doc_date' => now()->subDays((int) $days)->startOfDay()->addHours(9), 'doc_type' => 1,
                    'doc_cat' => 'STOCK', 'ref_doc_type' => 'MANUAL', 'warehouse_id' => (int) $whId, 'notes' => 'Opening stock for the warehouse map (dummy).'], $lines, self::BY);
                DB::connection('tm2inv')->table($class === 'RM' ? 'rm_stock' : 'fg_stock')->where('doc_id', $id)->update(['data_origin' => 'DUMMY']);
                $r = $post->postDocument($class, $id, self::BY);
                if (! $r['posted']) {
                    throw new RuntimeException("Stock In {$class} #{$id}: ".$r['error']);
                }
            }
        }
        DB::connection('tm2inv')->table('rm_stockcard')->where('create_by', self::BY)->update(['data_origin' => 'DUMMY']);
        DB::connection('tm2inv')->table('fg_stockcard')->where('create_by', self::BY)->update(['data_origin' => 'DUMMY']);
    }

    // Label palet kedatangan lewat PalletService (aturan yang sama dengan layar Print Pallet Label).
    private function pallets($bins): void
    {
        $materials = MatRaw::withoutGlobalScopes()->whereIn('material_code', array_unique(array_column(self::PALLETS, 0)))->get()->keyBy('material_code');
        $lots = collect(self::RM_STOCK)->mapWithKeys(fn ($r) => [$r[0].'|'.$r[1] => $r[3]]);
        foreach (self::PALLETS as $n => [$code, $bin, $split, $prefix, $shelf]) {
            $days = (int) $lots[$code.'|'.$bin];
            $arrived = now()->subDays($days);
            $key = 'RM:'.$materials[$code]->material_id.':0:'.$bins[$bin]->location_id;
            $rows = [];
            foreach ($split as $i => $qty) {
                $rows[] = ['qty' => $qty, 'supplier_pallet_code' => $prefix.'-'.$arrived->format('ymd').'-'.str_pad((string) ($n * 3 + $i + 1), 3, '0', STR_PAD_LEFT),
                    'lot_no' => 'LOT-'.$arrived->format('ymd'), 'expiry_date' => $arrived->copy()->addDays($shelf)->toDateString()];
            }
            PalletService::create($key, $rows, ['arrived_at' => $arrived->toDateString(), 'notes' => null], self::BY);
        }
    }

    private function transfers($bins): void
    {
        $svc = new StockTransferService;
        $wh = WhWarehouse::query()->where('created_by', self::BY)->pluck('id_warehouse', 'warehouse_code');
        $materials = MatRaw::withoutGlobalScopes()->whereIn('material_code', collect(self::TRANSFERS)->flatMap(fn ($t) => array_column($t[6], 0))->unique()->all())->get()->keyBy('material_code');
        foreach (self::TRANSFERS as $n => [$from, $to, $reason, $notes, $carrier, $confirm, $lines]) {
            $t = InvTransfer::create(['transfer_date' => now()->subDays($confirm ? max(0, 2 - $n) : 0)->toDateString(), 'from_warehouse_id' => $wh[$from], 'to_warehouse_id' => $wh[$to], 'reason' => $reason,
                'notes' => $notes, 'carried_by' => $carrier, 'created_by' => self::BY, 'updated_by' => self::BY]);
            foreach ($lines as $i => [$code, $src, $dst, $qty]) {
                $m = $materials[$code];
                InvTransferLine::create(['transfer_id' => $t->id_transfer, 'line_no' => $i + 1, 'stock_class' => 'RM', 'item_id' => $m->material_id, 'item_code' => $m->material_code,
                    'item_name' => $m->material_name, 'from_location_id' => $bins[$src]->location_id, 'to_location_id' => $bins[$dst]->location_id, 'qty' => $qty,
                    'uom_code' => $m->uom_stock, 'created_by' => self::BY, 'updated_by' => self::BY]);
            }
            if ($confirm && ($error = $svc->confirm($t, self::BY))) {
                throw new RuntimeException('Transfer: '.$error);
            }
        }
        DB::connection('tm2inv')->table('rm_stockcard')->where('create_by', self::BY)->update(['data_origin' => 'DUMMY']);
    }
}
