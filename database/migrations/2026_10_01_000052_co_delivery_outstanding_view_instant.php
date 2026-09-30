<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// v_co_delivery_outstanding (picker DO) mengambil customer lewat INNER JOIN quo_quotation -> CO INSTANT (tanpa Quotation,
// aturan A.2 docs/globaluserrules.md) tidak pernah muncul di Delivery Order. Customer kini dari co_order.customer_id
// (kolom sejak CO INSTANT), quotation hanya cadangan untuk CO lama yang kolomnya masih kosong.
return new class extends Migration
{
    private function create(string $customer, string $join): void
    {
        DB::connection('tm2sdi')->statement("CREATE OR REPLACE VIEW v_co_delivery_outstanding AS
            SELECT cod.id_delivery AS co_delivery_id, co.id_order AS co_order_id, co.co_no, {$customer} AS customer_id,
                   coi.id_item AS co_order_item_id, coi.description AS item_description, coi.qty_unit, coi.unit_price,
                   cod.ship_qty, cod.ship_date, co.doc_status AS co_status,
                   (cod.ship_qty - COALESCE(dl_sum.qty_allocated, 0)) AS qty_available
            FROM co_order_delivery cod
            JOIN co_order_item coi ON coi.id_item = cod.item_id
            JOIN co_order co ON co.id_order = cod.order_id
            {$join} JOIN quo_quotation q ON q.id_quotation = co.quotation_id
            LEFT JOIN (SELECT co_delivery_id, SUM(qty_do) AS qty_allocated FROM do_line WHERE status IN ('PLANNED','SHIPPED') GROUP BY co_delivery_id) dl_sum
                   ON dl_sum.co_delivery_id = cod.id_delivery
            WHERE cod.status <> 'SHIPPED'");
    }

    public function up(): void
    {
        $this->create('COALESCE(co.customer_id, q.customer_id)', 'LEFT');
    }

    public function down(): void
    {
        $this->create('q.customer_id', '');
    }
};
