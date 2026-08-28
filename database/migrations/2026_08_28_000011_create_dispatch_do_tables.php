<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Modul 05 Dispatch (dispatch_tier1..4.md + dispatch_rules.yaml, spec user
// 2026-08-26/28). do_delivery/do_line = SATU objek data, DUA cetakan
// (Delivery Order + Surat Jalan) -- DISPATCH-00. Tabel BARU murni, TIDAK ADA
// ALTER ke co_order/co_order_item/co_order_delivery (dispatch_tier3.md §1).
//
// DEVIASI dari dispatch_tier3.md §2 (dicatat, bukan diam-diam): kolom alamat
// kirim BUKAN `ship_address_id -> tm2mst.cust_address` (tabel itu TIDAK ADA
// di skema nyata) -- diselaraskan ke pola `co_order` yang SUDAH LIVE:
// ship_area_id/ship_area_level (geo hierarchy, dipakai App\Livewire\AddressPicker)
// + ship_address_label/ship_address_detail/ship_postal_code. Sama exact 5
// kolom, cuma ship_address_id diganti ship_area_id+ship_area_level. Dicek
// dulu ke co_order.php & co/entry.blade.php sebelum menulis ini (bukan tebak).
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2sdi');

        if (! $db->hasTable('do_delivery')) {
            $db->create('do_delivery', function (Blueprint $table) {
                $table->bigIncrements('id_delivery');
                $table->string('do_no', 20)->nullable()->unique()->comment('DO/YY/##### -- digenerate saat Confirm, NULL selama DRAFT');
                $table->date('do_date');

                $table->unsignedBigInteger('customer_id')->comment('LOGICAL -> tm2mst.cust_customer.id_customer -- KUNCI DOKUMEN, terkunci begitu baris pertama ditarik');
                $table->unsignedInteger('ship_area_id')->nullable()->comment('geo hierarchy, sama pola co_order.ship_area_id (AddressPicker)');
                $table->string('ship_area_level', 20)->nullable();
                $table->string('ship_address_label', 255)->nullable();
                $table->text('ship_address_detail')->nullable();
                $table->string('ship_postal_code', 20)->nullable();

                $table->date('scheduled_ship_date')->nullable();
                $table->dateTime('actual_ship_date')->nullable();

                $table->string('status', 20)->default('DRAFT')->comment('DRAFT|CONFIRMED|SHIPPED|CANCELLED');

                $table->boolean('price_change_flag')->default(false)->comment('1 kalau minimal 1 do_line price_overridden=1');
                $table->string('price_change_approval_status', 20)->nullable()->comment('RESERVED Fase 2 -- TIDAK dipakai v1, selalu NULL');

                $table->string('notes', 255)->nullable()->comment('wajib diisi kalau status=CANCELLED');

                $table->timestamps();
                $table->string('created_by', 100)->nullable();
                $table->string('updated_by', 100)->nullable();

                $table->index('customer_id', 'idx_do_customer');
                $table->index('status', 'idx_do_status');
            });
        }

        if (! $db->hasTable('do_line')) {
            $db->create('do_line', function (Blueprint $table) {
                $table->bigIncrements('id_line');
                $table->unsignedBigInteger('delivery_id');
                $table->unsignedSmallInteger('line_no');

                $table->unsignedBigInteger('co_order_id')->comment('FISIK -> co_order.id_order -- referensi CO asal PER BARIS');
                $table->unsignedBigInteger('co_order_item_id')->comment('FISIK -> co_order_item.id_item -- KUNCI IDENTITAS baris, no-merge (DISPATCH-02)');
                $table->unsignedBigInteger('co_delivery_id')->nullable()->comment('FISIK -> co_order_delivery.id_delivery -- baris jadwal yang dipenuhi');

                $table->string('item_description', 255)->comment('SNAPSHOT co_order_item.description saat ditarik');
                $table->decimal('qty_do', 14, 2)->comment('qty benar-benar dikirim, <= qty_available saat ditarik');
                $table->string('qty_unit', 20)->comment('SNAPSHOT co_order_item.qty_unit');
                $table->decimal('unit_price', 18, 4)->comment('SNAPSHOT co_order_item.unit_price, ATAU harga override');
                $table->decimal('subtotal', 18, 2)->default(0)->comment('qty_do * unit_price, dihitung aplikasi saat simpan');

                $table->boolean('price_overridden')->default(false);
                $table->decimal('original_unit_price', 18, 4)->nullable()->comment('diisi SEKALI saat override pertama, tidak ditimpa lagi');

                $table->decimal('qty_invoiced', 14, 2)->default(0)->comment('RESERVED Fase 2 (Invoicing) -- selalu 0 di v1');

                $table->string('status', 20)->default('PLANNED')->comment('PLANNED|SHIPPED|CANCELLED');
                $table->unsignedBigInteger('split_from_line_id')->nullable()->comment('RESERVED -- self-FK split baris, TIDAK dipakai v1');

                $table->timestamps();
                $table->string('created_by', 100)->nullable();
                $table->string('updated_by', 100)->nullable();

                $table->index('delivery_id', 'idx_doline_delivery');
                $table->index('co_order_id', 'idx_doline_co_order');
                $table->index('co_order_item_id', 'idx_doline_co_item');
                $table->index('co_delivery_id', 'idx_doline_co_delivery');

                $table->foreign('delivery_id', 'fk_doline_delivery')->references('id_delivery')->on('do_delivery')->onDelete('cascade');
                $table->foreign('co_order_id', 'fk_doline_co_order')->references('id_order')->on('co_order')->onDelete('restrict');
                $table->foreign('co_order_item_id', 'fk_doline_co_item')->references('id_item')->on('co_order_item')->onDelete('restrict');
                $table->foreign('co_delivery_id', 'fk_doline_co_deliv')->references('id_delivery')->on('co_order_delivery')->onDelete('restrict');
                $table->foreign('split_from_line_id', 'fk_doline_split')->references('id_line')->on('do_line')->onDelete('set null');
            });

            DB::connection('tm2sdi')->statement('ALTER TABLE do_line ADD CONSTRAINT chk_doline_qty CHECK (qty_do > 0)');
        }

        // ── VIEW 1: v_co_delivery_outstanding -- sumber picker (Tier2 §A.2) ──
        DB::connection('tm2sdi')->statement("
            CREATE OR REPLACE VIEW v_co_delivery_outstanding AS
            SELECT
              cod.id_delivery                                  AS co_delivery_id,
              co.id_order                                       AS co_order_id,
              co.co_no                                          AS co_no,
              q.customer_id                                     AS customer_id,
              coi.id_item                                       AS co_order_item_id,
              coi.description                                   AS item_description,
              coi.qty_unit                                      AS qty_unit,
              coi.unit_price                                    AS unit_price,
              cod.ship_qty                                      AS ship_qty,
              cod.ship_date                                     AS ship_date,
              co.doc_status                                     AS co_status,
              cod.ship_qty - COALESCE(dl_sum.qty_allocated, 0)  AS qty_available
            FROM co_order_delivery cod
            JOIN co_order_item coi ON coi.id_item  = cod.item_id
            JOIN co_order       co  ON co.id_order  = cod.order_id
            JOIN quo_quotation   q   ON q.id_quotation = co.quotation_id
            LEFT JOIN (
              SELECT co_delivery_id, SUM(qty_do) AS qty_allocated
              FROM do_line
              WHERE status IN ('PLANNED','SHIPPED')
              GROUP BY co_delivery_id
            ) dl_sum ON dl_sum.co_delivery_id = cod.id_delivery
            WHERE cod.status <> 'SHIPPED'
        ");

        // ── VIEW 2: v_do_list -- sumber grid DO List (Tier 4 §2) ──
        // Cross-DB view tm2sdi->tm2mst DIIZINKAN dgn privilege tm2user yang
        // ada (dibuktikan sesi ini di tm2inv->tm2mst utk v_rm_onhand dkk) --
        // tidak perlu fallback ke join level Eloquent (Tier3 §4.2 catatan).
        DB::connection('tm2sdi')->statement("
            CREATE OR REPLACE VIEW v_do_list AS
            SELECT
              d.id_delivery,
              d.do_no,
              d.do_date,
              d.status,
              d.customer_id,
              c.customer_name,
              COUNT(dl.id_line)                              AS line_count,
              COUNT(DISTINCT dl.co_order_id)                 AS co_count,
              COALESCE(SUM(dl.qty_do * dl.unit_price), 0)    AS total_value
            FROM do_delivery d
            LEFT JOIN do_line dl ON dl.delivery_id = d.id_delivery AND dl.status <> 'CANCELLED'
            LEFT JOIN tm2mst.cust_customer c ON c.id_customer = d.customer_id
            GROUP BY d.id_delivery, d.do_no, d.do_date, d.status, d.customer_id, c.customer_name
        ");
    }

    public function down(): void
    {
        DB::connection('tm2sdi')->statement('DROP VIEW IF EXISTS v_do_list');
        DB::connection('tm2sdi')->statement('DROP VIEW IF EXISTS v_co_delivery_outstanding');

        Schema::connection('tm2sdi')->dropIfExists('do_line');
        Schema::connection('tm2sdi')->dropIfExists('do_delivery');
    }
};
