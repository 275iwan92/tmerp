<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Product Market 2 tingkat (user 2026-09-26): Kategori Produk (induk, mis. Dus Makanan) -> Detail Kategori
// (mis. PPL, Dus Nasi) -> Ukuran Pasaran milik detail. Kategori tidak punya Tipe Produk; detail wajib.
// Nama dieline 2 tipe baru diganti istilah internasional supaya mudah dicari di Pacdora.
return new class extends Migration
{
    private const GROUPS = [
        'DUS_MAKANAN' => ['Dus Makanan', ['DUS_NASI_KOTAK', 'PAPER_LUNCH_BOX', 'BOX_MAKANAN', 'BOX_KUE']],
        'KOSMETIK_RETAIL' => ['Kemasan Kosmetik & Retail', ['BOX_KOSMETIK', 'BOX_SEPATU', 'BOX_SOUVENIR', 'BOX_SILINDER']],
        'FARMASI' => ['Kemasan Farmasi', ['BOX_FARMASI']],
        'LABEL_TAG' => ['Label & Tag', ['HANG_TAG']],
    ];

    public function up(): void
    {
        $s = Schema::connection('tm2mst');
        $s->table('prod_market', function (Blueprint $t) {
            $t->unsignedBigInteger('parent_id')->nullable()->after('id_market')->comment('null = Kategori Produk; isi = Detail Kategori');
            $t->foreign('parent_id')->references('id_market')->on('prod_market')->nullOnDelete();
        });
        DB::connection('tm2mst')->statement('ALTER TABLE prod_market MODIFY type_code VARCHAR(30) NULL COMMENT "-> prod_type.type_code; wajib untuk Detail Kategori"');

        $mst = DB::connection('tm2mst');
        $now = now();
        $sort = 0;
        foreach (self::GROUPS as $code => [$label, $children]) {
            $id = $mst->table('prod_market')->where('market_code', $code)->value('id_market') ?? $mst->table('prod_market')->insertGetId([
                'market_code' => $code, 'market_label' => $label, 'type_code' => null, 'sort_order' => $sort += 10,
                'activate' => 1, 'created_at' => $now, 'updated_at' => $now, 'created_by' => 'system',
            ]);
            $mst->table('prod_market')->whereIn('market_code', $children)->update(['parent_id' => $id]);
        }

        $mst->table('prod_type')->where('type_code', 'RICE_BOX')->update(['type_name' => 'Food Tray Box with Lid']);
        $mst->table('prod_type')->where('type_code', 'PAPER_LUNCH_BOX')->update(['type_name' => 'Paper Lunch Box']);
    }

    public function down(): void
    {
        $mst = DB::connection('tm2mst');
        $mst->table('prod_market')->update(['parent_id' => null]);
        $mst->table('prod_market')->whereIn('market_code', array_keys(self::GROUPS))->delete();
        Schema::connection('tm2mst')->table('prod_market', function (Blueprint $t) {
            $t->dropForeign(['parent_id']);
            $t->dropColumn('parent_id');
        });
        $mst->table('prod_type')->where('type_code', 'RICE_BOX')->update(['type_name' => 'Dus Nasi / Rice Box (R-series)']);
        $mst->table('prod_type')->where('type_code', 'PAPER_LUNCH_BOX')->update(['type_name' => 'Paper Lunch Box (PPL)']);
    }
};
