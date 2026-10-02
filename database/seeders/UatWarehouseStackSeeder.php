<?php

namespace Database\Seeders;

use App\Models\Inventory\InvPallet;
use App\Models\Inventory\InvPalletLog;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\RmStockD;
use App\Models\Master\MatRaw;
use App\Models\Master\WhLoc;
use App\Models\Master\WhZone;
use App\Services\Inventory\PalletService;
use App\Services\Inventory\StockDocumentService;
use App\Services\Inventory\StockPostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Dummy tumpukan dan sisa rim (user 2026-10-03: "selain posisi X dan Y juga ada stack, terutama untuk barang yang tersisa
// 1 atau 2 rim"; "sisa ditumpuk begitu saja dalam satu palet"; "yang banyak problem adalah kertas, ambri, sheet"), di atas
// gedung A10 buatan UatWarehouseSeeder:
//   A10-PPR-L06-05  tumpukan sisa: enam pak dari enam item (kertas, ambri, sheet), dua di antaranya sisa lama
//   A10-PPR-L03-01  satu pak sisa lama di atas dua palet dead stock
//   A10-PPR-L04-02  palet tertua tertimbun palet yang baru datang (Buried)
// Item-nya sengaja yang BELUM punya stok contoh: Stock In hari kemarin membuat item itu "baru bergerak", jadi memakai item
// yang sudah ada akan menghapus contoh dead stock milik UatWarehouseSeeder.
// Stok masuk lewat Stock In sungguhan (kemarin), label lewat PalletService dengan tanggal tiba masing-masing.
// Tinggi tumpuk area gedung contoh disamakan dengan UatWarehouseSeeder::STACK (area kertas = dua palet bertumpuk).
// Hanya ddt*; diulang = dummy ini dihapus lalu dibuat lagi.
//   php artisan db:seed --class=UatWarehouseStackSeeder --env=mockup --force
class UatWarehouseStackSeeder extends Seeder
{
    private const BY = 'uat_wh_stack';

    // Urut dari BAWAH ke atas per bin: [kode bin, kode bahan, qty, jenis satuan, tiba berapa hari lalu, harga satuan]
    private const UNITS = [
        ['A10-PPR-L06-05', 'PZ00671', 500, 'PACK', 332, 2600], ['A10-PPR-L06-05', 'XZ00397', 60, 'PACK', 224, 4200], ['A10-PPR-L06-05', 'PZ00977', 280, 'PACK', 122, 2100],
        ['A10-PPR-L06-05', 'PZ00643', 1000, 'PACK', 50, 700], ['A10-PPR-L06-05', 'PZ00662', 350, 'PACK', 15, 3400], ['A10-PPR-L06-05', 'XZ00716', 400, 'PACK', 1, 450],
        ['A10-PPR-L03-01', 'PZ00088', 600, 'PACK', 205, 2000],
        ['A10-PPR-L04-02', 'PKA02002', 3000, 'PALLET', 320, 1500], ['A10-PPR-L04-02', 'PZ00642', 5000, 'PALLET', 6, 600],
    ];

    public function run(): void
    {
        if (! str_starts_with(DB::connection('tm2mst')->getDatabaseName(), 'ddt') || ! str_starts_with(DB::connection('tm2inv')->getDatabaseName(), 'ddt')) {
            throw new RuntimeException('UatWarehouseStackSeeder only runs on the dummy database (ddt*): add --env=mockup.');
        }
        self::clean();
        foreach (UatWarehouseSeeder::STACK as $zone => $height) {
            WhZone::query()->where('created_by', 'uat_warehouse')->where('zone_code', $zone)->update(['stack_height_m' => $height]);
        }
        $bins = WhLoc::query()->whereIn('location_code', array_unique(array_column(self::UNITS, 0)))->get()->keyBy('location_code');
        $materials = MatRaw::withoutGlobalScopes()->whereIn('material_code', array_unique(array_column(self::UNITS, 1)))->get()->keyBy('material_code');
        $lines = [];
        foreach (self::UNITS as [$bin, $code, $qty, , , $price]) {
            $loc = $bins[$bin] ?? throw new RuntimeException("Bin {$bin} not found: run UatWarehouseSeeder first.");
            $m = $materials[$code] ?? throw new RuntimeException("Material {$code} not found.");
            $lines[] = ['material_id' => $m->material_id, 'qty' => $qty, 'uom_code' => $m->uom_stock ?: 'LBR', 'unit_price' => $price, 'qty_unitprice' => $qty * $price,
                'lot_no' => null, 'location_id' => $loc->location_id];
        }
        // Kemarin: tidak ada baris kartu bertanggal hari ini, supaya dokumen yang dicoba user hari ini selalu baris terakhir.
        $docs = new StockDocumentService;
        $id = $docs->saveDraft('RM', null, ['doc_no' => $docs->nextDocNo('RM', 'IN'), 'doc_date' => now()->subDay()->startOfDay()->addHours(10), 'doc_type' => 1, 'doc_cat' => 'STOCK',
            'ref_doc_type' => 'MANUAL', 'warehouse_id' => (int) $bins->first()->warehouse_id, 'notes' => 'Remnants and stacked pallets for the Stack Monitor (dummy).'], $lines, self::BY);
        DB::connection('tm2inv')->table('rm_stock')->where('doc_id', $id)->update(['data_origin' => 'DUMMY']);
        $r = (new StockPostingService)->postDocument('RM', $id, self::BY);
        if (! $r['posted']) {
            throw new RuntimeException("Stock In RM #{$id}: ".$r['error']);
        }
        DB::connection('tm2inv')->table('rm_stockcard')->where('create_by', self::BY)->update(['data_origin' => 'DUMMY']);

        foreach (self::UNITS as [$bin, $code, $qty, $kind, $days]) {
            $arrived = now()->subDays($days);
            PalletService::create('RM:'.$materials[$code]->material_id.':0:'.$bins[$bin]->location_id, [['qty' => $qty, 'lot_no' => 'LOT-'.$arrived->format('ymd')]],
                ['arrived_at' => $arrived->toDateString(), 'unit_kind' => $kind, 'notes' => $kind === 'PACK' ? 'Remnant after use' : null], self::BY);
        }
    }

    // Hapus dummy seeder ini (label -> dokumen stok + kartunya) dan kembalikan cur_stock; tumpukan bin yang ditinggalkan dirapikan.
    public static function clean(): void
    {
        $pallets = InvPallet::query()->where('created_by', self::BY)->get();
        InvPalletLog::query()->whereIn('pallet_id', $pallets->pluck('id_pallet')->all() ?: [0])->delete();
        InvPallet::query()->whereIn('id_pallet', $pallets->pluck('id_pallet')->all() ?: [0])->delete();
        foreach ($pallets->pluck('location_id')->unique() as $locationId) {
            PalletService::compact((int) $locationId);
        }
        $docIds = RmStock::query()->where('create_by', self::BY)->pluck('doc_id')->all();
        $lines = RmStockD::query()->whereIn('doc_id', $docIds ?: [0])->get();
        RmStockcard::query()->where('reference_name', 'tm2inv.rm_stock_d')->whereIn('reference_detail_id', $lines->pluck('doc_detail_id')->all() ?: [0])->delete();
        foreach ($lines->groupBy('material_id') as $itemId => $rows) {
            MatRaw::withoutGlobalScopes()->whereKey($itemId)->update(['cur_stock' => DB::raw('COALESCE(cur_stock, 0) - ('.(float) $rows->sum('qty').')')]);
        }
        RmStockD::query()->whereIn('doc_id', $docIds ?: [0])->delete();
        RmStock::query()->whereIn('doc_id', $docIds ?: [0])->delete();
    }
}
