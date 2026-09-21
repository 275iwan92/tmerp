<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// CO dua mode (keputusan user 2026-09-21), penamaan disamakan dengan ProDev/
// ProCost: INTEGRATED (lahir dari Quotation, input minimal) vs INSTANT (manual
// dari nol, wajib Master Product per baris). Dua JALUR INPUT ke dokumen yang
// SAMA -- bukan dua alur bisnis, jadi tidak ada tabel/kolom kembar per mode.
//
// quotation_id jadi nullable: prasyarat struktural yang sama persis dengan yang
// dibutuhkan migrasi histori CO lama dari tm2old (lihat memory
// project_tm2old_co_po_migration) -- UNIQUE index-nya tetap aman karena MySQL
// mengizinkan banyak baris NULL pada unique index.
//
// customer_id diisi di KEDUA mode (bukan cuma INSTANT) -- sebelumnya customer CO
// cuma bisa dicapai lewat 2 hop relasi lintas-DB (co_order -> quo_quotation ->
// tm2mst.cust_customer) yang memaksa resolver manual di grid. Denormalisasi 1
// kolom ini menghapus hop pertama dan konsisten dengan prinsip snapshot §4.
return new class extends Migration
{
    public function up(): void
    {
        // doctrine/dbal tidak terpasang -- ubah nullability lewat SQL mentah.
        DB::connection('tm2sdi')->statement('ALTER TABLE co_order MODIFY quotation_id BIGINT UNSIGNED NULL');

        Schema::connection('tm2sdi')->table('co_order', function (Blueprint $t) {
            $t->string('entry_mode', 12)->default('INTEGRATED')->after('co_date');
            $t->unsignedBigInteger('customer_id')->nullable()->after('quotation_id');
            $t->unsignedBigInteger('sales_person_id')->nullable()->after('customer_id');
            $t->string('payment_term', 50)->nullable()->after('sales_person_id');
            $t->string('currency_code', 5)->nullable()->after('payment_term');
            $t->string('share_token', 40)->nullable()->unique()->after('notes');
            $t->timestamp('share_expires_at')->nullable()->after('share_token');
            $t->timestamp('shared_at')->nullable()->after('share_expires_at');
            $t->index('customer_id');
            $t->index('entry_mode');
        });

        Schema::connection('tm2sdi')->table('co_order_item', function (Blueprint $t) {
            // FK logis ke tm2mst.prod_product (lintas-DB, tidak boleh constraint
            // fisik). WAJIB terisi untuk baris PRODUCT di CO mode INSTANT --
            // divalidasi di layar/service, bukan di DB.
            $t->unsignedBigInteger('product_id')->nullable()->after('inquiry_id');
            // Toleransi qty cetak (snapshot dari prod_product.qty_limit_min/max
            // saat baris dibuat): jumlah kirim yang sah menyimpang segini dari
            // qty pesan -- praktik baku offset printing, ada di CO legacy
            // (sdi_co_detail.qty_limit_min/max) tapi hilang di V2.
            $t->decimal('qty_min', 12, 2)->nullable()->after('qty_unit');
            $t->decimal('qty_max', 12, 2)->nullable()->after('qty_min');
            $t->index('product_id');
        });

        // Backfill: semua CO yang sudah ada lahir dari Quotation.
        DB::connection('tm2sdi')->statement(
            'UPDATE co_order c JOIN quo_quotation q ON q.id_quotation = c.quotation_id
             SET c.customer_id = q.customer_id,
                 c.payment_term = q.payment_term,
                 c.currency_code = q.currency_code
             WHERE c.quotation_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('co_order_item', function (Blueprint $t) {
            $t->dropIndex(['product_id']);
            $t->dropColumn(['product_id', 'qty_min', 'qty_max']);
        });

        Schema::connection('tm2sdi')->table('co_order', function (Blueprint $t) {
            $t->dropIndex(['customer_id']);
            $t->dropIndex(['entry_mode']);
            $t->dropColumn(['entry_mode', 'customer_id', 'sales_person_id', 'payment_term', 'currency_code', 'share_token', 'share_expires_at', 'shared_at']);
        });

        DB::connection('tm2sdi')->statement('ALTER TABLE co_order MODIFY quotation_id BIGINT UNSIGNED NOT NULL');
    }
};
