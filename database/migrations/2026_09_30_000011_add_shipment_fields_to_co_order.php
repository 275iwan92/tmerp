<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Field pengiriman CO v1 (tm2old.sdi_co / sdi_co_detail) yang belum ada di v2 (user 2026-09-30: "check structure CO
// yang lama, adakah untuk shipment, jika ada tambahkan"). Recipient/shipper/forwarder di v1 = kode customer (mst_vendor),
// jadi di v2 menunjuk tm2mst.cust_customer (FK logis lintas-DB, tanpa constraint — CLAUDE.md §2).
// qty_minim = flag Ya/Tidak "order masuk qty minimum" (beda dari qty_min = angka batas).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('co_order', function (Blueprint $t) {
            $t->string('shipment_method', 10)->nullable()->after('customer_po_no')->comment('DELIVER | PICKUP (v1 sdi_co.shipment_method)');
            $t->unsignedBigInteger('recipient_customer_id')->nullable()->after('shipment_method')->comment('penerima barang bila beda dari customer -> tm2mst.cust_customer');
            $t->unsignedBigInteger('shipper_customer_id')->nullable()->after('recipient_customer_id')->comment('shipper -> tm2mst.cust_customer (v1 shipper_code)');
            $t->unsignedBigInteger('forwarder_customer_id')->nullable()->after('shipper_customer_id')->comment('forwarder -> tm2mst.cust_customer (v1 forwarder_code)');
        });
        Schema::connection('tm2sdi')->table('co_order_item', function (Blueprint $t) {
            $t->boolean('qty_minim')->default(false)->after('qty_max')->comment('order masuk qty minimum (v1 sdi_co_detail.qty_minim)');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('co_order', function (Blueprint $t) {
            $t->dropColumn(['shipment_method', 'recipient_customer_id', 'shipper_customer_id', 'forwarder_customer_id']);
        });
        Schema::connection('tm2sdi')->table('co_order_item', function (Blueprint $t) {
            $t->dropColumn('qty_minim');
        });
    }
};
