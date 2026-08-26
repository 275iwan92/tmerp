<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// inventory_tier3.md §9 -- VIEW pelaporan. Dua penyesuaian nyata dari teks asli
// (diverifikasi ke skema riil, bukan ditebak):
// 1. Produk/part FG-SFG di project ini adalah tm2mst.prod_product (PK
//    id_product, product_code/product_name) + tm2mst.prod_part (PK id_part,
//    part_code/part_name) -- BUKAN m_product/m_product_parts yang disebut
//    teks asli (tabel itu tidak ada sama sekali).
// 2. m_wh_loc/location_id/location_name/location_code sudah cocok sejak
//    migration 2026_08_21_000008 (Warehouse diselaraskan lebih dulu).
return new class extends Migration
{
    public function up(): void
    {
        $inv = DB::connection('tm2inv');

        $inv->statement('DROP VIEW IF EXISTS v_rm_onhand');
        $inv->statement(<<<'SQL'
            CREATE VIEW v_rm_onhand AS
            SELECT sc.material_id, sc.location_id, sc.warehouse_id,
                   w.warehouse_name, l.location_name, l.location_code, l.is_usable,
                   sc.uom_code, sc.qty_eb AS qty_onhand, sc.value_eb AS value_onhand,
                   sc.avg_unitprice, sc.doc_date AS last_movement
            FROM rm_stockcard sc
            JOIN (
              SELECT material_id, location_id, MAX(CONCAT(DATE_FORMAT(doc_date,'%Y%m%d%H%i%s'), LPAD(doc_id,12,'0'))) AS mx
              FROM rm_stockcard GROUP BY material_id, location_id
            ) x ON x.material_id = sc.material_id
               AND x.location_id = sc.location_id
               AND x.mx = CONCAT(DATE_FORMAT(sc.doc_date,'%Y%m%d%H%i%s'), LPAD(sc.doc_id,12,'0'))
            LEFT JOIN tm2mst.m_wh_loc    l ON l.location_id  = sc.location_id
            LEFT JOIN tm2mst.m_warehouse w ON w.id_warehouse = sc.warehouse_id
        SQL);

        $inv->statement('DROP VIEW IF EXISTS v_rm_available');
        $inv->statement(<<<'SQL'
            CREATE VIEW v_rm_available AS
            SELECT o.material_id, o.location_id, o.warehouse_id, o.uom_code,
                   o.is_usable, o.qty_onhand,
                   IFNULL(b.qty_booked_open, 0) AS qty_booked,
                   CASE WHEN o.is_usable = 0 THEN 0
                        ELSE o.qty_onhand - IFNULL(b.qty_booked_open, 0) END AS qty_available
            FROM v_rm_onhand o
            LEFT JOIN (
              SELECT d.material_id, d.location_id, SUM(d.qty_book - d.qty_issued) AS qty_booked_open
              FROM rm_booking_d d
              JOIN rm_booking   h ON h.booking_id = d.booking_id
              WHERE h.stat_confirm = 1 AND h.stat_cancel = 0 AND h.stat_closed = 0
                AND d.stat_cancel = 0 AND d.qty_book > d.qty_issued
              GROUP BY d.material_id, d.location_id
            ) b ON b.material_id = o.material_id AND b.location_id <=> o.location_id
        SQL);

        $inv->statement('DROP VIEW IF EXISTS v_rm_out_returnable');
        $inv->statement(<<<'SQL'
            CREATE VIEW v_rm_out_returnable AS
            SELECT h.doc_id, h.doc_no, h.doc_date, h.wo_id, h.ref_doc_no,
                   d.doc_detail_id, d.material_id, m.material_code, m.material_name,
                   d.lot_no, d.location_id, l.location_name, d.uom_code,
                   d.action_type, d.qty AS qty_issued, d.qty_returned,
                   (d.qty - d.qty_returned) AS qty_returnable
            FROM rm_stock h
            JOIN rm_stock_d d ON d.doc_id = h.doc_id
            LEFT JOIN tm2mst.m_raw_material m ON m.material_id = d.material_id
            LEFT JOIN tm2mst.m_wh_loc       l ON l.location_id = d.location_id
            WHERE h.doc_type = -1 AND h.doc_cat = 'STOCK'
              AND h.flow_status = 'POSTED'
              AND d.action_type IN ('ISSUE','SUBSTITUTE')
              AND (d.qty - d.qty_returned) > 0
        SQL);

        $inv->statement('DROP VIEW IF EXISTS v_inv_movement');
        $inv->statement(<<<'SQL'
            CREATE VIEW v_inv_movement AS
            SELECT 'RM' AS stock_class, sc.doc_id, sc.doc_date, sc.doc_type,
                   sc.material_id AS item_id, m.material_code AS item_code, m.material_name AS item_name,
                   NULL AS parts_id, sc.warehouse_id, w.warehouse_name,
                   sc.location_id, l.location_name, l.is_usable, sc.lot_no, sc.uom_code,
                   h.doc_no, h.doc_cat, h.ref_doc_no,
                   sc.qty_bb, sc.qty_in, sc.qty_out, sc.qty_adj, sc.qty_eb,
                   sc.value_eb, sc.avg_unitprice, sc.period_id
            FROM rm_stockcard sc
            LEFT JOIN rm_stock_d d ON d.doc_detail_id = sc.reference_detail_id
                                  AND sc.reference_name = 'tm2inv.rm_stock_d'
            LEFT JOIN rm_stock   h ON h.doc_id = d.doc_id
            LEFT JOIN tm2mst.m_raw_material m ON m.material_id  = sc.material_id
            LEFT JOIN tm2mst.m_wh_loc       l ON l.location_id  = sc.location_id
            LEFT JOIN tm2mst.m_warehouse    w ON w.id_warehouse = sc.warehouse_id

            UNION ALL

            SELECT 'SFG', sc.doc_id, sc.doc_date, sc.doc_type,
                   sc.parts_id, pp.part_code, pp.part_name,
                   sc.parts_id, sc.warehouse_id, w.warehouse_name,
                   sc.location_id, l.location_name, l.is_usable, sc.lot_no, sc.uom_code,
                   h.doc_no, h.doc_cat, h.ref_doc_no,
                   sc.qty_bb, sc.qty_in, sc.qty_out, sc.qty_adj, sc.qty_eb,
                   sc.value_eb, sc.avg_unitprice, sc.period_id
            FROM sfg_stockcard sc
            LEFT JOIN sfg_stock_d d ON d.doc_detail_id = sc.reference_detail_id
                                   AND sc.reference_name = 'tm2inv.sfg_stock_d'
            LEFT JOIN sfg_stock   h ON h.doc_id = d.doc_id
            LEFT JOIN tm2mst.prod_part pp ON pp.id_part = sc.parts_id
            LEFT JOIN tm2mst.m_wh_loc     l ON l.location_id  = sc.location_id
            LEFT JOIN tm2mst.m_warehouse  w ON w.id_warehouse = sc.warehouse_id

            UNION ALL

            SELECT 'FG', sc.doc_id, sc.doc_date, sc.doc_type,
                   sc.product_id, p.product_code, p.product_name,
                   sc.parts_id, sc.warehouse_id, w.warehouse_name,
                   sc.location_id, l.location_name, l.is_usable, sc.lot_no, sc.uom_code,
                   h.doc_no, h.doc_cat, h.ref_doc_no,
                   sc.qty_bb, sc.qty_in, sc.qty_out, sc.qty_adj, sc.qty_eb,
                   sc.value_eb, sc.avg_unitprice, sc.period_id
            FROM fg_stockcard sc
            LEFT JOIN fg_stock_d d ON d.doc_detail_id = sc.reference_detail_id
                                  AND sc.reference_name = 'tm2inv.fg_stock_d'
            LEFT JOIN fg_stock   h ON h.doc_id = d.doc_id
            LEFT JOIN tm2mst.prod_product p ON p.id_product   = sc.product_id
            LEFT JOIN tm2mst.m_wh_loc     l ON l.location_id  = sc.location_id
            LEFT JOIN tm2mst.m_warehouse  w ON w.id_warehouse = sc.warehouse_id
        SQL);

        $inv->statement('DROP VIEW IF EXISTS v_inv_wo_reconcile');
        $inv->statement(<<<'SQL'
            CREATE VIEW v_inv_wo_reconcile AS
            SELECT h.wo_id, h.ref_doc_no AS wo_no, d.material_id,
                   m.material_code, m.material_name, d.uom_code,
                   SUM(CASE WHEN h.doc_cat = 'STOCK' AND h.doc_type = -1 THEN d.qty ELSE 0 END) AS qty_issued,
                   SUM(CASE WHEN h.doc_cat = 'RETURN' AND d.return_condition = 'GOOD'  THEN d.qty ELSE 0 END) AS qty_return_good,
                   SUM(CASE WHEN h.doc_cat = 'RETURN' AND d.return_condition = 'SCRAP' THEN d.qty ELSE 0 END) AS qty_return_scrap,
                   SUM(CASE WHEN h.doc_cat = 'STOCK' AND h.doc_type = -1 THEN d.qty ELSE 0 END)
                   - SUM(CASE WHEN h.doc_cat = 'RETURN' THEN d.qty ELSE 0 END) AS qty_net_used
            FROM rm_stock h
            JOIN rm_stock_d d ON d.doc_id = h.doc_id
            LEFT JOIN tm2mst.m_raw_material m ON m.material_id = d.material_id
            WHERE h.flow_status = 'POSTED'
              AND h.wo_id IS NOT NULL
              AND IFNULL(d.action_type,'ISSUE') <> 'PURCHASE'
            GROUP BY h.wo_id, h.ref_doc_no, d.material_id, m.material_code, m.material_name, d.uom_code
        SQL);

        // §9.6 -- v_fg_onhand / v_sfg_onhand: pola sama dgn v_rm_onhand (urut
        // doc_date+doc_id, bukan MAX(doc_id) spt versi legacy yang tak pernah
        // diimpor -- tidak ada yang perlu di-drop, langsung dibuat versi benar.
        $inv->statement('DROP VIEW IF EXISTS v_fg_onhand');
        $inv->statement(<<<'SQL'
            CREATE VIEW v_fg_onhand AS
            SELECT sc.product_id, sc.parts_id, sc.location_id, sc.warehouse_id,
                   w.warehouse_name, l.location_name, l.location_code, l.is_usable,
                   sc.uom_code, sc.qty_eb AS qty_onhand, sc.value_eb AS value_onhand,
                   sc.avg_unitprice, sc.doc_date AS last_movement
            FROM fg_stockcard sc
            JOIN (
              SELECT c_sku_key, location_id, MAX(CONCAT(DATE_FORMAT(doc_date,'%Y%m%d%H%i%s'), LPAD(doc_id,12,'0'))) AS mx
              FROM fg_stockcard GROUP BY c_sku_key, location_id
            ) x ON x.c_sku_key = sc.c_sku_key
               AND x.location_id = sc.location_id
               AND x.mx = CONCAT(DATE_FORMAT(sc.doc_date,'%Y%m%d%H%i%s'), LPAD(sc.doc_id,12,'0'))
            LEFT JOIN tm2mst.m_wh_loc    l ON l.location_id  = sc.location_id
            LEFT JOIN tm2mst.m_warehouse w ON w.id_warehouse = sc.warehouse_id
        SQL);

        $inv->statement('DROP VIEW IF EXISTS v_sfg_onhand');
        $inv->statement(<<<'SQL'
            CREATE VIEW v_sfg_onhand AS
            SELECT sc.parts_id, sc.location_id, sc.warehouse_id,
                   w.warehouse_name, l.location_name, l.location_code, l.is_usable,
                   sc.uom_code, sc.qty_eb AS qty_onhand, sc.value_eb AS value_onhand,
                   sc.avg_unitprice, sc.doc_date AS last_movement
            FROM sfg_stockcard sc
            JOIN (
              SELECT parts_id, location_id, MAX(CONCAT(DATE_FORMAT(doc_date,'%Y%m%d%H%i%s'), LPAD(doc_id,12,'0'))) AS mx
              FROM sfg_stockcard GROUP BY parts_id, location_id
            ) x ON x.parts_id = sc.parts_id
               AND x.location_id = sc.location_id
               AND x.mx = CONCAT(DATE_FORMAT(sc.doc_date,'%Y%m%d%H%i%s'), LPAD(sc.doc_id,12,'0'))
            LEFT JOIN tm2mst.m_wh_loc    l ON l.location_id  = sc.location_id
            LEFT JOIN tm2mst.m_warehouse w ON w.id_warehouse = sc.warehouse_id
        SQL);
    }

    public function down(): void
    {
        $inv = DB::connection('tm2inv');
        foreach (['v_rm_onhand', 'v_rm_available', 'v_rm_out_returnable', 'v_inv_movement', 'v_inv_wo_reconcile', 'v_fg_onhand', 'v_sfg_onhand'] as $v) {
            $inv->statement("DROP VIEW IF EXISTS {$v}");
        }
    }
};
