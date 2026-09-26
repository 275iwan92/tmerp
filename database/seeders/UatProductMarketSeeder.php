<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Data uji manual Lookup Product Market (user 2026-09-26): banyak kategori packaging lengkap dengan detail,
// Tipe Produk (dieline), bahan #1 Paper / #2 Sheet (kategori bahan baku 5 tingkat), warna, jenjang qty, ukuran pasaran.
// Ukuran bertanda (perkiraan) = ukuran umum pasar yang belum diverifikasi ke sumber resmi; lainnya dari riset:
// rokok 85x55x20 / 98x56x20 / 100x45x15 mm (thelegacyprinting, urgentboxes), parfum 30ml 5x5x10 & 100ml 7x7x14 cm
// (paperlicious, pacmora), sepatu 18x14x8 s/d 33x20x13 cm (pamanpack, wellenprint).
// Hanya di ddt*. Diulang = reset (baris created_by = uat-seeder dihapus dulu).
//   php artisan db:seed --class=UatProductMarketSeeder --env=mockup
class UatProductMarketSeeder extends Seeder
{
    private const BY = 'uat-seeder';

    // Grup => [label, [detail...]]. Detail: [kode, label, tipe, paper L2, sheet L2|null, warna, jenjang qty, catatan, ukuran[]].
    // Ukuran: [nama, P, L, T (mm), extra dims] ; grup/detail yang sudah ada (migration) dipakai ulang, tidak dibuat dobel.
    private function plan(): array
    {
        return [
            'KEMASAN_ROKOK' => ['Kemasan Rokok', [
                ['DUS_ROKOK', 'Dus Rokok (bungkus)', 'RTE_BOX', 'Ivory', null, '4/0', '100000,250000,500000',
                    'Bungkus rokok; Ivory 230-250 gr + hot print/UV. Flip-top belum ada dieline-nya, sementara RTE.', [
                        ['Isi 12 (perkiraan)', 85, 45, 20], ['Isi 16 (perkiraan)', 85, 55, 18], ['King Size isi 20', 85, 55, 20],
                        ['100s isi 20', 98, 56, 20], ['Slim isi 20', 100, 45, 15]]],
                ['SLOP_ROKOK', 'Slop Rokok (10 bungkus)', 'CARD_BOX', 'Duplex', null, '4/0', '10000,25000,50000',
                    'Karton pembungkus 10 bungkus (2 x 5).', [['Slop King Size (perkiraan)', 280, 90, 45], ['Slop Slim (perkiraan)', 230, 102, 32]]],
            ]],
            'KEMASAN_PARFUM' => ['Kemasan Parfum', [
                ['BOX_PARFUM', 'Box Parfum Botol', 'RTE_BOX', 'Ivory', null, '4/0', '1000,3000,5000',
                    'Ivory 300-350 gr + laminasi/emboss; ukuran pas botol (snug fit).', [
                        ['30 ml', 50, 50, 100], ['50 ml (perkiraan)', 60, 60, 110], ['100 ml', 70, 70, 140]]],
                ['BOX_PARFUM_PREMIUM', 'Box Parfum Premium (Tutup-Alas)', 'BOX_WITH_LID', 'Art carton', null, '4/0', '500,1000,3000',
                    'Tutup-alas, art carton dilapis greyboard (hard box).', [
                        ['50 ml (perkiraan)', 80, 80, 120, ['lid_height' => 40]], ['100 ml (perkiraan)', 100, 100, 150, ['lid_height' => 50]]]],
            ]],
            'KEMASAN_FASHION' => ['Kemasan Sepatu & Fashion', [
                ['BOX_SEPATU', null, 'BOX_WITH_LID', 'Duplex', null, '4/0', '1000,3000,5000', 'Box sepatu tutup-alas duplex.', [
                    ['Anak', 180, 140, 80, ['lid_height' => 30]], ['Regular 36-39', 210, 170, 90, ['lid_height' => 35]],
                    ['Medium 40-42', 250, 170, 100, ['lid_height' => 40]], ['Wanita', 300, 180, 100, ['lid_height' => 40]], ['Pria', 330, 200, 130, ['lid_height' => 50]]]],
                ['BOX_SEPATU_MAILER', 'Box Sepatu Corrugated (Mailer)', 'MAILER', 'Kraft', 'SHEF', '1/0', '1000,3000,5000',
                    'Kraft + E-flute, kuat untuk kirim online.', [['Large 43-46', 320, 170, 120], ['Pria', 330, 200, 130]]],
                ['BOX_KEMEJA', 'Box Kemeja / Kaos', 'SHALLOW_BOX', 'Duplex', null, '4/0', '1000,3000,5000', 'Box tipis untuk kaos/kemeja lipat.', [
                    ['Kaos (perkiraan)', 300, 250, 50], ['Kemeja (perkiraan)', 350, 270, 60]]],
            ]],
            'KOSMETIK_RETAIL' => [null, [
                ['BOX_SERUM', 'Box Serum / Skincare Botol', 'STE_BOX', 'Ivory', null, '4/0', '3000,5000,10000', 'Ivory 300 gr + laminasi doff.', [
                    ['Serum 30 ml', 40, 40, 110], ['Skincare 50 ml', 50, 50, 150], ['Toner 100 ml (perkiraan)', 55, 55, 160]]],
                ['BOX_KRIM_JAR', 'Box Krim Jar', 'STE_BOX', 'Ivory', null, '4/0', '3000,5000,10000', 'Box kubus untuk jar krim.', [
                    ['Jar 15 g (perkiraan)', 45, 45, 40], ['Jar 30 g (perkiraan)', 60, 60, 50]]],
                ['BOX_KOSMETIK', null, 'BOX_WITH_LID', 'Art carton', null, '4/0', '1000,3000,5000', 'Box set kosmetik tutup-alas.', [
                    ['Set kecil (perkiraan)', 150, 100, 50, ['lid_height' => 25]], ['Set besar (perkiraan)', 220, 160, 60, ['lid_height' => 30]]]],
                ['BOX_SILINDER', null, 'ROUND_BOX', 'Art carton', null, '4/0', '1000,3000,5000', 'Tube / hat box untuk teh premium, parfum, kosmetik.', [
                    ['Tube D6 x 15 (perkiraan)', 0, 0, 150, ['diameter' => 60, 'lid_height' => 40]], ['Hat box D20 x 12 (perkiraan)', 0, 0, 120, ['diameter' => 200, 'lid_height' => 50]]]],
                ['BOX_LIPSTIK', 'Box Lipstik', 'STE_BOX', 'Ivory', null, '4/0', '5000,10000,20000', null, [['Lipstik (perkiraan)', 25, 25, 90]]],
            ]],
            'FARMASI' => [null, [
                ['BOX_FARMASI', null, 'STE_BOX', 'Duplex', null, '2/0', '10000,50000,100000', 'Kemasan obat umum.', [
                    ['Tablet botol kecil (perkiraan)', 45, 45, 90], ['Salep tube 15 g (perkiraan)', 30, 25, 120]]],
                ['BOX_OBAT_STRIP', 'Box Obat Strip / Blister', 'STE_BOX', 'Duplex', null, '2/0', '10000,50000,100000', 'Duplex 250-310 gr, cetak 1-2 warna + nomor batch.', [
                    ['Isi 5 strip (perkiraan)', 105, 55, 30], ['Isi 10 strip (perkiraan)', 105, 55, 60]]],
                ['BOX_SIRUP', 'Box Botol Sirup', 'RTE_BOX', 'Duplex', null, '4/0', '10000,50000,100000', null, [
                    ['Sirup 60 ml (perkiraan)', 45, 45, 120], ['Sirup 100 ml (perkiraan)', 55, 55, 135]]],
                ['BOX_SUPLEMEN', 'Box Botol Suplemen', 'CRASH_LOCK', 'Ivory', null, '4/0', '5000,10000,20000', 'Alas kunci otomatis untuk botol berat.', [
                    ['Botol 60 kapsul (perkiraan)', 60, 60, 120]]],
            ]],
            'KEMASAN_MINUMAN' => ['Kemasan Minuman & Kopi', [
                ['BOX_TEH_CELUP', 'Box Teh Celup', 'CARD_BOX', 'Duplex', null, '4/0', '10000,50000,100000', null, [
                    ['Isi 25 kantong (perkiraan)', 120, 65, 75], ['Isi 50 kantong (perkiraan)', 130, 75, 120]]],
                ['BOX_KOPI_SACHET', 'Box Kopi Sachet', 'STE_BOX', 'Duplex', null, '4/0', '10000,50000,100000', null, [
                    ['Isi 10 sachet (perkiraan)', 130, 85, 50], ['Isi 20 sachet (perkiraan)', 130, 85, 95]]],
            ]],
            'DUS_MAKANAN' => [null, [
                ['BOX_KUE', null, 'SHALLOW_BOX', 'Duplex', 'SHEF', '4/0', '1000,3000,5000', 'Pizza/kue: duplex food grade + E-flute agar tidak melendut.', [
                    ['Pizza 8 inch', 210, 210, 40], ['Pizza 10 inch', 260, 260, 40], ['Pizza 12 inch', 310, 310, 40], ['Kue Tart 20 cm (perkiraan)', 220, 220, 100]]],
                ['BOX_MAKANAN', null, 'CARD_BOX', 'Duplex', null, '4/0', '3000,5000,10000', 'Box makanan ringan/snack tuck end.', [
                    ['Snack 100 g (perkiraan)', 120, 40, 170], ['Cookies 200 g (perkiraan)', 150, 60, 200]]],
                ['BOX_DONAT', 'Box Donat', 'SHALLOW_BOX', 'Duplex', null, '4/0', '1000,3000,5000', null, [
                    ['Isi 6 (perkiraan)', 260, 180, 60], ['Isi 12 (perkiraan)', 350, 260, 60]]],
                ['DUS_MARTABAK', 'Dus Martabak', 'SHALLOW_BOX', 'Duplex', null, '2/0', '1000,3000,5000', null, [
                    ['Martabak Manis (perkiraan)', 260, 260, 50], ['Martabak Telur (perkiraan)', 220, 150, 50]]],
            ]],
            'KEMASAN_ELEKTRONIK' => ['Kemasan Elektronik & Gadget', [
                ['BOX_HANDPHONE', 'Box Handphone', 'BOX_WITH_LID', 'Art carton', null, '4/0', '1000,3000,5000', 'Hard box art carton + greyboard.', [
                    ['Smartphone 6 inch (perkiraan)', 170, 90, 55, ['lid_height' => 55]]]],
                ['BOX_TWS', 'Box Earphone / TWS', 'BOX_WITH_LID', 'Art carton', null, '4/0', '1000,3000,5000', null, [
                    ['TWS (perkiraan)', 100, 100, 50, ['lid_height' => 50]]]],
                ['BOX_POWERBANK', 'Box Powerbank', 'STE_BOX', 'Ivory', null, '4/0', '1000,3000,5000', null, [['10.000 mAh (perkiraan)', 150, 80, 30]]],
            ]],
            'KEMASAN_ECOMMERCE' => ['Kemasan E-commerce / Pengiriman', [
                ['MAILER_ONLINE', 'Mailer Box Online Shop', 'MAILER', 'Kraft', 'SHEF', '1/0', '1000,3000,5000', 'Kraft + E-flute, sablon/1 warna.', [
                    ['S (perkiraan)', 200, 150, 50], ['M (perkiraan)', 250, 200, 80], ['L (perkiraan)', 300, 250, 100]]],
            ]],
            'KEMASAN_HAMPERS' => ['Hampers & Gift', [
                ['BOX_HAMPERS', 'Box Hampers', 'BOX_WITH_LID', 'Art carton', 'SHEF', '4/0', '500,1000,3000', 'Musiman (Lebaran/Natal).', [
                    ['30 x 30 (perkiraan)', 300, 300, 100, ['lid_height' => 50]], ['40 x 30 (perkiraan)', 400, 300, 120, ['lid_height' => 60]]]],
                ['BOX_SOUVENIR', null, 'GIFT_BOX', 'Art carton', null, '4/0', '1000,3000,5000', null, [
                    ['Kecil (perkiraan)', 100, 100, 100], ['Sedang (perkiraan)', 150, 150, 80]]],
            ]],
            'LABEL_TAG' => [null, [
                ['HANG_TAG', null, 'HANG_TAG', 'Art carton', null, '4/4', '5000,10000,20000', null, [['5 x 9 cm', 50, 90, 0], ['6 x 10 cm', 60, 100, 0]]],
                ['LABEL_STIKER', 'Label / Stiker Produk', 'LABEL_STIKER', 'Stiker bontax', null, '4/0', '5000,10000,50000', 'Stiker bontax/vinyl, potong kiss-cut.', [
                    ['5 x 5 cm', 50, 50, 0], ['10 x 5 cm', 100, 50, 0]]],
            ]],
        ];
    }

    private $mst;

    public function run(): void
    {
        $this->mst = DB::connection('tm2mst');
        if (! str_starts_with($this->mst->getDatabaseName(), 'ddt')) {
            throw new RuntimeException('UatProductMarketSeeder hanya boleh di database dummy ddt* (--env=mockup).');
        }

        $cat = fn (string $root, ?string $name) => $name ? $this->mst->table('mat_category as c')->join('mat_category as p', 'p.id_category', '=', 'c.parent_id')
            ->whereNull('p.parent_id')->where('p.category_name', $root)->where('c.category_name', $name)->value('c.id_category') : null;
        $now = now();
        $counts = ['groups' => 0, 'details' => 0, 'sizes' => 0];

        $this->mst->transaction(function () use ($cat, $now, &$counts) {
            $this->reset();
            $gSort = (int) $this->mst->table('prod_market')->whereNull('parent_id')->where('sort_order', '<', 999)->max('sort_order');

            foreach ($this->plan() as $gCode => [$gLabel, $details]) {
                $gid = $this->mst->table('prod_market')->where('market_code', $gCode)->value('id_market');
                if (! $gid) {
                    $gid = $this->mst->table('prod_market')->insertGetId(['market_code' => $gCode, 'market_label' => $gLabel, 'sort_order' => $gSort += 10,
                        'activate' => 1, 'created_at' => $now, 'updated_at' => $now, 'created_by' => self::BY]);
                    $counts['groups']++;
                }

                $dSort = (int) $this->mst->table('prod_market')->where('parent_id', $gid)->max('sort_order');
                foreach ($details as [$code, $label, $type, $paper, $sheet, $color, $tiers, $notes, $sizes]) {
                    $values = ['parent_id' => $gid, 'type_code' => $type, 'paper_category_id' => $cat('Paper', $paper), 'sheet_category_id' => $cat('Sheet', $sheet),
                        'default_color' => $color, 'default_tiers' => $tiers, 'updated_at' => $now];
                    $id = $this->mst->table('prod_market')->where('market_code', $code)->value('id_market');
                    if ($id) {
                        // Detail bawaan migration: simpan nilai lama agar reset bisa mengembalikannya.
                        $old = (array) $this->mst->table('prod_market')->where('id_market', $id)->first(array_keys($values + ['notes' => null]));
                        $this->mst->table('prod_market')->where('id_market', $id)->update($values + ['notes' => json_encode(['uat_restore' => $old])]);
                    } else {
                        $id = $this->mst->table('prod_market')->insertGetId($values + ['market_code' => $code, 'market_label' => $label, 'notes' => $notes,
                            'sort_order' => $dSort += 10, 'activate' => 1, 'created_at' => $now, 'created_by' => self::BY]);
                        $counts['details']++;
                    }

                    foreach ($sizes as $i => $sz) {
                        [$name, $l, $w, $h] = $sz;
                        $dims = array_filter(['product_length' => $l, 'product_width' => $w, 'product_height' => $h], fn ($v) => $v > 0) + ($sz[4] ?? []);
                        $sizeId = $this->mst->table('prod_type_size')->insertGetId([
                            'type_code' => $type, 'market_id' => $id, 'size_code' => rtrim(substr($code.'_'.preg_replace('/[^A-Z0-9]+/', '_', strtoupper($name)), 0, 40), '_'),
                            'size_label' => $name.' ('.implode(' x ', array_map(fn ($v) => str_replace('.', ',', (string) ($v / 10)), array_filter([$l, $w, $sz[4]['diameter'] ?? 0, $h]))).' cm)',
                            'sort_order' => ($i + 1) * 10, 'activate' => 1, 'created_at' => $now, 'updated_at' => $now, 'created_by' => self::BY,
                        ]);
                        foreach ($dims as $k => $v) {
                            $this->mst->table('prod_type_size_val')->insert(['type_size_id' => $sizeId, 'param_key' => $k, 'value_num' => $v, 'created_at' => $now, 'updated_at' => $now]);
                        }
                        $counts['sizes']++;
                    }
                }
            }
        });

        $this->command?->info("Product Market UAT: {$counts['groups']} kategori baru, {$counts['details']} detail baru, {$counts['sizes']} ukuran pasaran di {$this->mst->getDatabaseName()}.");
    }

    private function reset(): void
    {
        $sizes = $this->mst->table('prod_type_size')->where('created_by', self::BY)->pluck('id_type_size');
        $this->mst->table('prod_type_size_val')->whereIn('type_size_id', $sizes)->delete();
        $this->mst->table('prod_type_size')->whereIn('id_type_size', $sizes)->delete();

        // Detail bawaan migration yang sempat diubah seeder dikembalikan ke nilai lamanya.
        foreach ($this->mst->table('prod_market')->where('notes', 'like', '{"uat_restore"%')->get() as $m) {
            $this->mst->table('prod_market')->where('id_market', $m->id_market)->update(json_decode($m->notes, true)['uat_restore']);
        }

        $this->mst->table('prod_market')->where('created_by', self::BY)->whereNotNull('parent_id')->delete();
        $this->mst->table('prod_market')->where('created_by', self::BY)->delete();
    }
}
