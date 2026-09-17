<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// BOP per langkah: mesin, target default, waste & toleransi (qty dan %) --
// permintaan user 2026-09-17. Persen default per aturan (prod_bop_rule, bisa
// diubah admin); per part tersimpan di prod_part_process / iqp_part_process
// (bisa di-override officer). Qty tidak disimpan -- selalu dihitung dari qty
// order sehingga tidak basi saat order berubah.
//
// Persen awal = ESTIMASI UMUM industri, bukan data pabrik ini (ditulis di
// note aturan) -- wajib dikoreksi dari data waste aktual (prd_wo_operation_
// output_defect) begitu cukup.
//
// Sekalian: v_do_list menampilkan jenis surat jalan & nama tujuan subkon.
return new class extends Migration
{
    // process_name => [waste %, toleransi %]
    private const DEFAULTS = [
        'POTONG' => [0.5, 0],
        'CETAK' => [3, 1],
        'QC CETAK' => [0.5, 0],
        'VARNISH' => [1, 0.5],
        'LAMINATING DOFT' => [1, 0.5],
        'LAMINATING GLOSSY' => [1, 0.5],
        'UV' => [1, 0.5],
        'HOT PRINT' => [2, 0.5],
        'FOIL STAMPING' => [2, 0.5],
        'LAMINASI' => [1, 0.5],
        'PLONG' => [1.5, 0.5],
        'EMBOSS' => [0.5, 0],
        'DEBOSS' => [0.5, 0],
        'KUPAS' => [0.5, 0],
        'LEM MESIN' => [1, 0.5],
        'HITUNG' => [0, 0],
        'WRAPPING' => [0, 0],
    ];

    public function up(): void
    {
        Schema::connection('tm2mst')->table('prod_bop_rule', function (Blueprint $t) {
            $t->decimal('waste_pct', 5, 2)->nullable()->after('machine_role');
            $t->decimal('tolerance_pct', 5, 2)->nullable()->after('waste_pct');
        });

        foreach ([['tm2mst', 'prod_part_process'], ['tm2sdi', 'iqp_part_process']] as [$conn, $table]) {
            Schema::connection($conn)->table($table, function (Blueprint $t) {
                $t->decimal('waste_pct', 5, 2)->nullable()->after('qty_factor');
                $t->decimal('tolerance_pct', 5, 2)->nullable()->after('waste_pct');
                $t->decimal('target_qty_per_hour', 12, 2)->nullable()->after('tolerance_pct')->comment('snapshot target default');
            });
        }

        $db = DB::connection('tm2mst');
        $ids = $db->table('prod_process')->pluck('id_process', 'process_name');

        foreach (self::DEFAULTS as $name => [$waste, $tol]) {
            if (! isset($ids[$name])) {
                continue;
            }
            $db->table('prod_bop_rule')->where('process_id', $ids[$name])->update([
                'waste_pct' => $waste,
                'tolerance_pct' => $tol,
                'note' => DB::raw("CONCAT_WS(' · ', NULLIF(note, ''), 'waste/toleransi = estimasi awal')"),
            ]);
        }

        DB::connection('tm2sdi')->statement(<<<'SQL'
            CREATE OR REPLACE VIEW tm2sdi.v_do_list AS
            SELECT d.id_delivery, d.do_no, d.do_type, d.do_date, d.status, d.customer_id, d.supplier_id,
                   COALESCE(c.customer_name, s.supplier_name) AS customer_name,
                   d.subcon_process,
                   COUNT(dl.id_line) AS line_count,
                   COUNT(DISTINCT dl.co_order_id) AS co_count,
                   COALESCE(SUM(dl.qty_do * dl.unit_price), 0) AS total_value
            FROM tm2sdi.do_delivery d
            LEFT JOIN tm2sdi.do_line dl ON dl.delivery_id = d.id_delivery AND dl.status <> 'CANCELLED'
            LEFT JOIN tm2mst.cust_customer c ON c.id_customer = d.customer_id
            LEFT JOIN tm2mst.sup_supplier s ON s.id_supplier = d.supplier_id
            GROUP BY d.id_delivery, d.do_no, d.do_type, d.do_date, d.status, d.customer_id, d.supplier_id,
                     c.customer_name, s.supplier_name, d.subcon_process
        SQL);
    }

    public function down(): void
    {
        DB::connection('tm2sdi')->statement(<<<'SQL'
            CREATE OR REPLACE VIEW tm2sdi.v_do_list AS
            SELECT d.id_delivery, d.do_no, d.do_date, d.status, d.customer_id, c.customer_name,
                   COUNT(dl.id_line) AS line_count,
                   COUNT(DISTINCT dl.co_order_id) AS co_count,
                   COALESCE(SUM(dl.qty_do * dl.unit_price), 0) AS total_value
            FROM tm2sdi.do_delivery d
            LEFT JOIN tm2sdi.do_line dl ON dl.delivery_id = d.id_delivery AND dl.status <> 'CANCELLED'
            LEFT JOIN tm2mst.cust_customer c ON c.id_customer = d.customer_id
            GROUP BY d.id_delivery, d.do_no, d.do_date, d.status, d.customer_id, c.customer_name
        SQL);

        foreach ([['tm2mst', 'prod_part_process'], ['tm2sdi', 'iqp_part_process']] as [$conn, $table]) {
            Schema::connection($conn)->table($table, fn (Blueprint $t) => $t->dropColumn(['waste_pct', 'tolerance_pct', 'target_qty_per_hour']));
        }

        Schema::connection('tm2mst')->table('prod_bop_rule', fn (Blueprint $t) => $t->dropColumn(['waste_pct', 'tolerance_pct']));
    }
};
