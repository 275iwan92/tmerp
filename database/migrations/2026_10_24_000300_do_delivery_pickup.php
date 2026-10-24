<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Keterangan pengambilan barang (Pickup) pada Delivery Order: nama pengambil, jenis kendaraan, nomor polisi (keputusan Jo 2026-10-24). Hanya terisi untuk DO dari CO berjenis Pickup.
 * Sumber hulu  : do_delivery (tm2sdi; ddtsdi di mockup lewat konfigurasi koneksi).
 * Input        : -
 * Output       : kolom pickup_name, pickup_vehicle, pickup_plate (nullable); down menghapusnya.
 * Dipakai oleh : DoDelivery::pickupNote(), DispatchService::confirm, layar Delivery Order, cetak DO dan Delivery Note.
 * Status       : Aktif.
 */
return new class extends Migration
{
    protected $connection = 'tm2sdi';

    public function up(): void
    {
        if (Schema::connection('tm2sdi')->hasColumn('do_delivery', 'pickup_name')) {
            return;
        }

        Schema::connection('tm2sdi')->table('do_delivery', function (Blueprint $t) {
            $t->string('pickup_name', 100)->nullable()->after('ship_postal_code')->comment('Pickup: nama pengambil barang, mis. Mr. XYZ');
            $t->string('pickup_vehicle', 40)->nullable()->after('pickup_name')->comment('Pickup: jenis kendaraan, bawaan truck');
            $t->string('pickup_plate', 20)->nullable()->after('pickup_vehicle')->comment('Pickup: nomor polisi, huruf besar, mis. N 12345 SAMPLE');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('do_delivery', function (Blueprint $t) {
            $t->dropColumn(['pickup_name', 'pickup_vehicle', 'pickup_plate']);
        });
    }
};
