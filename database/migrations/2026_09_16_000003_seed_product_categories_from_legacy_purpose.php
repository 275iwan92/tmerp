<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 5 kategori awal (Corrugated/Folding/Label/Hang Tag/Display) tidak cukup untuk
// menampung 13.972 produk legacy: goods_purpose di mst_item punya 559 nilai unik,
// dan kelompok terbesar yang tidak tertampung adalah cetakan komersial (poster,
// brosur, kalender, surat suara), plate/pisau, serta baris jasa/ongkos.
return new class extends Migration
{
    private const ROWS = [
        ['Cetakan Komersial', 'Poster, brosur, kalender, buku, surat suara -- cetakan lembaran non-kemasan'],
        ['Plate & Tooling', 'Plate cetak, pisau pond, film -- alat produksi yang ditagihkan ke customer'],
        ['Jasa & Ongkos', 'Baris ongkos kerja/jasa tanpa wujud barang (laminasi, plong, kirim)'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::ROWS as [$name, $desc]) {
            $exists = DB::connection('tm2mst')->table('prod_category')
                ->where('category_name', $name)->exists();

            if ($exists) {
                continue;
            }

            DB::connection('tm2mst')->table('prod_category')->insert([
                'category_name' => $name,
                'description' => $desc,
                'activate' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 'system',
                'updated_by' => 'system',
            ]);
        }
    }

    public function down(): void
    {
        foreach (self::ROWS as [$name]) {
            DB::connection('tm2mst')->table('prod_category')
                ->where('category_name', $name)
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('prod_product')
                    ->whereColumn('prod_product.category_id', 'prod_category.id_category'))
                ->delete();
        }
    }
};
