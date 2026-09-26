<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Nama Tipe Produk = nama dieline internasional seperti di Pacdora supaya mudah dicari (user 2026-09-27).
// Bahasa Indonesia hanya sebagai keterangan sesudah tanda " – ". Kode type_code tidak berubah.
return new class extends Migration
{
    private const NAMES = [
        'CARD_BOX' => 'Tuck End Box',
        'RTE_BOX' => 'Reverse Tuck End Box',
        'STE_BOX' => 'Straight Tuck End Box',
        'CRASH_LOCK' => 'Auto Bottom Box (Crash Lock)',
        'SLEEVE' => 'Sleeve Box',
        'GIFT_BOX' => 'Gift Box',
        'MATCH_BOX' => 'Drawer Box (Tray & Sleeve)',
        'MAILER' => 'Mailer Box',
        'SHALLOW_BOX' => 'Tray Box',
        'BOX_WITH_LID' => 'Lid and Base Box',
        'NESTABLE_TRAY' => 'Nestable Tray',
        'TRAY_INSERT' => 'Tray Insert',
        'MULTI_SHEET_BOX' => 'Multi Sheet Box',
        'PASSEPARTOUT' => 'Passepartout',
        'EXPLODING_BOX' => 'Exploding Box',
        'ROUND_BOX' => 'Round Box (Hat Box)',
        'CYLINDER' => 'Cylinder Box',
        'ELLIPTICAL_BOX' => 'Elliptical Box',
        'CONE_TRUNCATED' => 'Truncated Cone',
        'CONE_MITERED' => 'Mitered Cone',
        'SPHERE' => 'Sphere',
        'MILK_CARTON' => 'Gable Top Carton (Milk Carton)',
        'POLYGONAL_BOX' => 'Polygon Box',
        'POLYGONAL_BOX_LID' => 'Polygon Box with Lid',
        'POLYGONAL_PYRAMID' => 'Polygon Pyramid',
        'PYRAMID_BOX' => 'Pyramid Box',
        'GEM_STONE_BOX' => 'Gem Box',
        'PLATONIC_SOLIDS' => 'Platonic Solids',
        'PARALLELEPIPED' => 'Parallelepiped Box',
        'TRAPEZOID_BOX' => 'Trapezoid Box',
        'RHOMBUS_BOX' => 'Rhombus Box',
        'SOMA_CUBE' => 'Soma Cube',
        'CURVED_BOX' => 'Curved Box',
        'HEART_SHAPED_BOX' => 'Heart Box',
        'EGG_SHAPED_BOX' => 'Egg Box',
        'STAR_BOX' => 'Star Box',
        'STAR_SHAPE' => 'Star Shape',
        'CAKE_SLICE_BOX' => 'Cake Slice Box',
        'COFFIN' => 'Coffin Box',
        'PILLOW_PACK' => 'Pillow Box',
        'BAG' => 'Paper Bag',
        'ENVELOPE' => 'Envelope',
        'COUNTER_DISPLAY' => 'Counter Display Box',
        'FLOOR_DISPLAY' => 'Floor Display Stand',
        'WOBBLER' => 'Wobbler',
        'HANG_TAG' => 'Hang Tag',
        'KALENDER_DINDING' => 'Wall Calendar – Kalender Dinding',
        'KALENDER_DUDUK' => 'Desk Calendar – Kalender Meja',
        'BROSUR' => 'Brochure / Leaflet – Brosur',
        'FLYER' => 'Flyer',
        'POSTER' => 'Poster',
        'BUKU' => 'Book / Catalog – Buku',
        'MAJALAH' => 'Magazine – Majalah',
        'NOTA_INVOICE' => 'NCR Invoice – Nota',
        'LABEL_STIKER' => 'Label / Sticker – Stiker',
        'KARTU_NAMA' => 'Business Card – Kartu Nama',
        'AMPLOP_KOP' => 'Envelope & Letterhead – Amplop & Kop Surat',
        'RICE_BOX' => 'Food Box with Lid – Dus Nasi',
        'PAPER_LUNCH_BOX' => 'Paper Lunch Box – PPL',
        'CUSTOM' => 'Custom – Lainnya',
    ];

    public function up(): void
    {
        $db = DB::connection('tm2mst');
        foreach (self::NAMES as $code => $name) {
            $db->table('prod_type')->where('type_code', $code)->update(['type_name' => $name, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Nama lama tidak dikembalikan (hanya label; kode tetap).
    }
};
