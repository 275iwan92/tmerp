<?php

use App\Support\DbName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// View stok mengenal Stock Transfer (inv_transfer, 2026-10-02):
//  - v_rm_onhand / v_sfg_onhand / v_fg_onhand: kolom baru last_item_movement = tanggal pergerakan TERAKHIR item itu di
//    lokasi mana pun TANPA menghitung transfer (doc_type TF). Memindahkan barang ke bin lain bukan pemakaian, jadi stok
//    yang mati tidak boleh "hidup lagi" hanya karena dipindah. last_movement (baris kartu terakhir di lokasi itu) tetap.
//  - v_inv_movement: baris kartu dari transfer membawa nomor transfernya (doc_no) dan doc_cat 'TF'.
// Isi kolom lain tidak berubah (definisi lama disalin apa adanya; tabel master lewat DbName supaya situs dummy tetap ke ddt*).
return new class extends Migration
{
    // kelas => [kartu stok, kunci saldo, kunci item, kolom pilih tambahan]
    private const ONHAND = [
        'v_rm_onhand' => ['rm_stockcard', 'material_id', 'material_id', 'sc.material_id AS material_id'],
        'v_sfg_onhand' => ['sfg_stockcard', 'parts_id', 'parts_id', 'sc.parts_id AS parts_id'],
        'v_fg_onhand' => ['fg_stockcard', 'c_sku_key', 'product_id', 'sc.product_id AS product_id, sc.parts_id AS parts_id'],
    ];

    private function onhand(string $view, bool $withItemMovement): void
    {
        [$card, $key, $item, $select] = self::ONHAND[$view];
        $loc = DbName::table('tm2mst.wh_loc');
        $wh = DbName::table('tm2mst.wh_warehouse');
        $extra = $withItemMovement
            ? ", (SELECT MAX(s2.doc_date) FROM {$card} s2 WHERE s2.{$item} = sc.{$item} AND IFNULL(s2.doc_type, '') <> 'TF') AS last_item_movement"
            : '';
        DB::connection('tm2inv')->statement("CREATE OR REPLACE VIEW {$view} AS
            SELECT {$select}, sc.location_id AS location_id, sc.warehouse_id AS warehouse_id, w.warehouse_name AS warehouse_name,
                   l.location_name AS location_name, l.location_code AS location_code, l.is_usable AS is_usable, sc.uom_code AS uom_code,
                   sc.qty_eb AS qty_onhand, sc.value_eb AS value_onhand, sc.avg_unitprice AS avg_unitprice, sc.doc_date AS last_movement{$extra}
            FROM {$card} sc
            JOIN (SELECT {$key}, location_id, MAX(CONCAT(DATE_FORMAT(doc_date, '%Y%m%d%H%i%s'), LPAD(doc_id, 12, '0'))) AS mx
                  FROM {$card} GROUP BY {$key}, location_id) x
              ON x.{$key} = sc.{$key} AND x.location_id = sc.location_id AND x.mx = CONCAT(DATE_FORMAT(sc.doc_date, '%Y%m%d%H%i%s'), LPAD(sc.doc_id, 12, '0'))
            LEFT JOIN {$loc} l ON l.location_id = sc.location_id
            LEFT JOIN {$wh} w ON w.id_warehouse = sc.warehouse_id");
    }

    private function movement(bool $withTransfer): void
    {
        $loc = DbName::table('tm2mst.wh_loc');
        $wh = DbName::table('tm2mst.wh_warehouse');
        // kelas => [kartu, baris dokumen, kepala dokumen, kunci item, master item, pk master, kode, nama, parts_id]
        $classes = [
            'RM' => ['rm_stockcard', 'rm_stock_d', 'rm_stock', 'material_id', DbName::table('tm2mst.mat_raw'), 'material_id', 'material_code', 'material_name', 'NULL'],
            'SFG' => ['sfg_stockcard', 'sfg_stock_d', 'sfg_stock', 'parts_id', DbName::table('tm2mst.prod_part'), 'id_part', 'part_code', 'part_name', 'sc.parts_id'],
            'FG' => ['fg_stockcard', 'fg_stock_d', 'fg_stock', 'product_id', DbName::table('tm2mst.prod_product'), 'id_product', 'product_code', 'product_name', 'sc.parts_id'],
        ];
        $parts = [];
        foreach ($classes as $class => [$card, $line, $head, $item, $master, $pk, $code, $name, $partsId]) {
            $docNo = $withTransfer ? 'COALESCE(h.doc_no, tf.transfer_no)' : 'h.doc_no';
            $docCat = $withTransfer ? "COALESCE(h.doc_cat, IF(tf.id_transfer IS NULL, NULL, 'TF'))" : 'h.doc_cat';
            $join = $withTransfer
                ? "LEFT JOIN inv_transfer_line tl ON tl.id_transfer_line = sc.reference_detail_id AND sc.reference_name LIKE 'tm2inv.inv_transfer_line%'
                   LEFT JOIN inv_transfer tf ON tf.id_transfer = tl.transfer_id"
                : '';
            $parts[] = "SELECT '{$class}' AS stock_class, sc.doc_id AS doc_id, sc.doc_date AS doc_date, sc.doc_type AS doc_type, sc.{$item} AS item_id,
                       m.{$code} AS item_code, m.{$name} AS item_name, {$partsId} AS parts_id, sc.warehouse_id AS warehouse_id, w.warehouse_name AS warehouse_name,
                       sc.location_id AS location_id, l.location_name AS location_name, l.is_usable AS is_usable, sc.lot_no AS lot_no, sc.uom_code AS uom_code,
                       {$docNo} AS doc_no, {$docCat} AS doc_cat, h.ref_doc_no AS ref_doc_no, sc.qty_bb AS qty_bb, sc.qty_in AS qty_in, sc.qty_out AS qty_out,
                       sc.qty_adj AS qty_adj, sc.qty_eb AS qty_eb, sc.value_eb AS value_eb, sc.avg_unitprice AS avg_unitprice, sc.period_id AS period_id
                FROM {$card} sc
                LEFT JOIN {$line} d ON d.doc_detail_id = sc.reference_detail_id AND sc.reference_name = 'tm2inv.{$line}'
                LEFT JOIN {$head} h ON h.doc_id = d.doc_id
                {$join}
                LEFT JOIN {$master} m ON m.{$pk} = sc.{$item}
                LEFT JOIN {$loc} l ON l.location_id = sc.location_id
                LEFT JOIN {$wh} w ON w.id_warehouse = sc.warehouse_id";
        }
        DB::connection('tm2inv')->statement('CREATE OR REPLACE VIEW v_inv_movement AS '.implode(' UNION ALL ', $parts));
    }

    public function up(): void
    {
        foreach (array_keys(self::ONHAND) as $view) {
            $this->onhand($view, true);
        }
        $this->movement(true);
    }

    public function down(): void
    {
        foreach (array_keys(self::ONHAND) as $view) {
            $this->onhand($view, false);
        }
        $this->movement(false);
    }
};
