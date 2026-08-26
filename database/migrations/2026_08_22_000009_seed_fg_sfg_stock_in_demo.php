<?php

use App\Models\Master\MWarehouse;
use App\Models\Master\MWhLocation;
use App\Services\Inventory\StockDocumentService;
use App\Services\Inventory\StockPostingService;
use Illuminate\Database\Migrations\Migration;

// feedback_keep_dummy_data: layar Stock In baru wajib ada data uji di semua
// kelas, bukan cuma RM (yang sudah teruji via smoke test manual, doc_id 101,
// IN/26/00001). Posting SUNGGUHAN lewat StockPostingService yang sama persis
// dipakai layar -- bukan insert manual ke stockcard -- supaya alur (termasuk
// idempotency & sinkron cur_stock) ikut teruji, bukan cuma data statis.
return new class extends Migration
{
    public function up(): void
    {
        $docSvc = app(StockDocumentService::class);
        $postSvc = app(StockPostingService::class);

        $fgWarehouse = MWarehouse::where('warehouse_type', 'FG')->first();
        $fgLocation = MWhLocation::where('warehouse_id', $fgWarehouse->id_warehouse)->where('is_usable', true)->first();

        $fgDocNo = $docSvc->nextDocNo('FG', 'IN');
        $fgDocId = $docSvc->saveDraft('FG', null, [
            'doc_no' => $fgDocNo,
            'doc_date' => now()->subDay()->toDateString(),
            'doc_type' => 1,
            'doc_cat' => 'STOCK',
            'ref_doc_type' => 'MANUAL',
            'warehouse_id' => $fgWarehouse->id_warehouse,
            'notes' => 'Data uji Stock In FG -- via StockPostingService, bukan insert manual.',
        ], [
            ['product_id' => 1, 'qty' => 150, 'uom_code' => 'PCS', 'lot_no' => 'LOT-FGIN-0001', 'location_id' => $fgLocation->location_id, 'unit_price' => 0],
        ], 'system');
        $postSvc->postDocument('FG', $fgDocId, 'system');

        $sfgWarehouse = MWarehouse::where('warehouse_type', 'MIX')->first(); // belum ada tipe SFG khusus, lihat sidebarmenustandard.md #6
        $sfgLocation = MWhLocation::where('warehouse_id', $sfgWarehouse->id_warehouse)->where('is_usable', true)->first();
        $sfgPartId = \App\Models\Master\ProdPart::where('part_code', 'FB-DUPLEX-001-BODY')->value('id_part');

        $sfgDocNo = $docSvc->nextDocNo('SFG', 'IN');
        $sfgDocId = $docSvc->saveDraft('SFG', null, [
            'doc_no' => $sfgDocNo,
            'doc_date' => now()->subDay()->toDateString(),
            'doc_type' => 1,
            'doc_cat' => 'STOCK',
            'ref_doc_type' => 'MANUAL',
            'warehouse_id' => $sfgWarehouse->id_warehouse,
            'notes' => 'Data uji Stock In SFG -- via StockPostingService, bukan insert manual.',
        ], [
            ['parts_id' => $sfgPartId, 'qty' => 80, 'uom_code' => 'PCS', 'lot_no' => 'LOT-SFGIN-0001', 'location_id' => $sfgLocation->location_id, 'unit_price' => 0],
        ], 'system');
        $postSvc->postDocument('SFG', $sfgDocId, 'system');
    }

    public function down(): void
    {
        // Dokumen posted tidak "dibatalkan" via cancel() (spec INV-08: no-revise,
        // posted docs tak pernah diedit/dibatalkan) -- down() cuma hapus baris
        // demo secara langsung karena ini murni migration seed data uji.
        $inv = \Illuminate\Support\Facades\DB::connection('tm2inv');
        foreach (['fg' => 'LOT-FGIN-0001', 'sfg' => 'LOT-SFGIN-0001'] as $prefix => $lot) {
            $inv->table("{$prefix}_stockcard")->where('lot_no', $lot)->delete();
            $docId = $inv->table("{$prefix}_stock")->where('notes', 'like', 'Data uji Stock In%')->value('doc_id');
            if ($docId) {
                $inv->table("{$prefix}_stock_d")->where('doc_id', $docId)->delete();
                $inv->table("{$prefix}_stock")->where('doc_id', $docId)->delete();
            }
        }
    }
};
