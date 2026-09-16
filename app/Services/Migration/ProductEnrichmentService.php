<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\DB;

// Melengkapi prod_product dengan klasifikasi yang ikut hilang saat migrasi awal:
// customer_id, category_id, brand_name. ProductMigrationService sengaja hanya
// memindahkan kolom yang pasti (nama/uom/aktif); di sini dikerjakan bagian yang
// butuh pemetaan, dengan aturan yang bisa diaudit.
//
// Yang TETAP tidak disentuh: cm_length/cm_width/cm_height. Sumbernya
// (mst_item.length/width/height) cuma terisi 6-10% DAN satuannya campur --
// 1.131 baris ada di rentang 1-60 (cm) sementara 270 baris di rentang 60-2.000
// (mm), dengan outlier width 280.265. Tidak ada kolom satuan di sumber, jadi
// menebak per baris berisiko mengubah harga/HPP yang dihitung dari dimensi.
// Sama untuk min_stock (1 baris) dan price_last/price_avg (0 baris) -- kosong
// di sumber, bukan hilang saat migrasi.
class ProductEnrichmentService
{
    // goods_purpose punya 559 nilai unik -- dipetakan lewat kata, bukan daftar
    // lengkap. Urutan array = prioritas: entri pertama yang cocok menang, jadi
    // yang lebih spesifik ditaruh di atas ("PLATE" sebelum "CETAK", karena
    // "PLAT CETAK" harus jatuh ke Plate & Tooling bukan Cetakan Komersial).
    private const CATEGORY_RULES = [
        'Plate & Tooling' => ['PLATE', 'PLAT', 'PLONG', 'PISAU', 'FILM', 'DIECUT', 'POND'],
        'Jasa & Ongkos' => ['ONGKOS', 'JASA', 'BIAYA', 'LAMINASI'],
        'Label & Sticker' => ['STIKER', 'STICKER', 'LABEL', 'ETIKET', 'BARCODE', 'SEGEL'],
        'Hang Tag' => ['HANGTAG', 'TAG'],
        'Display & POP' => ['DISPLAY', 'BANNER', 'STANDEE', 'POP'],
        'Corrugated Box' => ['KARTON', 'CORRUGATED', 'KARDUS'],
        'Folding Box' => ['BOX', 'DUS', 'INNER', 'OUTER', 'IB', 'OB', 'AMBRI', 'SLEEVE'],
        'Cetakan Komersial' => ['CETAK', 'POSTER', 'BROSUR', 'KALENDER', 'BUKU', 'SURAT',
            'PRINTING', 'LEAFLET', 'FLYER', 'UNDANGAN', 'MAJALAH', 'KATALOG', 'AGENDA',
            'KUPON', 'AMPLOP', 'KARTU', 'NOTA', 'FAKTUR'],
    ];

    public function run(string $actor): array
    {
        ini_set('memory_limit', '1G');
        set_time_limit(1800);

        $report = [
            'products_scanned' => 0,
            'traits_written' => 0,
            'customer_matched' => 0,
            'customer_unmatched' => 0,
            'category_matched' => 0,
            'category_unmatched' => 0,
            'brand_written' => 0,
            'unmapped_belongto' => [],
            'unmapped_purpose' => [],
            'errors' => [],
        ];

        $nicknameMap = $this->customerNicknameMap();
        $categoryMap = $this->categoryNameMap();

        $unmappedBelongto = [];
        $unmappedPurpose = [];

        DB::connection('tm2old')->table('mst_item')
            ->where('as_fgs', 1)
            ->select('item_code', 'goods_belongto', 'goods_purpose', 'brand_name')
            ->orderBy('item_code')
            ->chunk(1000, function ($chunk) use (
                &$report, &$unmappedBelongto, &$unmappedPurpose,
                $nicknameMap, $categoryMap, $actor
            ) {
                $updates = [];

                foreach ($chunk as $item) {
                    $report['products_scanned']++;

                    $belongto = $this->blank($item->goods_belongto);
                    $purpose = $this->blank($item->goods_purpose);
                    $brand = $this->blank($item->brand_name);

                    if ($belongto === null && $purpose === null && $brand === null) {
                        continue;
                    }

                    $row = [
                        'old_goods_belongto' => $belongto,
                        'old_goods_purpose' => $purpose,
                        'brand_name' => $brand !== null ? mb_substr($brand, 0, 50) : null,
                        'updated_by' => $actor,
                    ];

                    if ($brand !== null) {
                        $report['brand_written']++;
                    }

                    if ($belongto !== null) {
                        $key = mb_strtoupper($belongto);
                        if (isset($nicknameMap[$key])) {
                            $row['customer_id'] = $nicknameMap[$key];
                            $report['customer_matched']++;
                        } else {
                            $report['customer_unmatched']++;
                            $unmappedBelongto[$belongto] = ($unmappedBelongto[$belongto] ?? 0) + 1;
                        }
                    }

                    if ($purpose !== null) {
                        $categoryName = $this->classify($purpose);
                        if ($categoryName && isset($categoryMap[$categoryName])) {
                            $row['category_id'] = $categoryMap[$categoryName];
                            $report['category_matched']++;
                        } else {
                            $report['category_unmatched']++;
                            $unmappedPurpose[$purpose] = ($unmappedPurpose[$purpose] ?? 0) + 1;
                        }
                    }

                    $updates[trim((string) $item->item_code)] = $row;
                }

                $report['traits_written'] += $this->applyUpdates($updates);
            });

        arsort($unmappedBelongto);
        arsort($unmappedPurpose);
        $report['unmapped_belongto'] = array_slice($unmappedBelongto, 0, 25, true);
        $report['unmapped_purpose'] = array_slice($unmappedPurpose, 0, 25, true);

        return $report;
    }

    /** @param  array<string,array<string,mixed>>  $updates */
    protected function applyUpdates(array $updates): int
    {
        $written = 0;

        DB::connection('tm2mst')->transaction(function () use ($updates, &$written) {
            foreach ($updates as $oldCode => $row) {
                // Kode item legacy banyak yang murni angka ("201907030004") -- PHP
                // sudah terlanjur mengubahnya jadi int saat dipakai sbg key array,
                // dan binding int bikin MySQL membandingkan kolom varchar secara
                // numerik (gagal di strict mode begitu ketemu kode beralfabet).
                $oldCode = (string) $oldCode;
                if ($oldCode === '') {
                    continue;
                }

                $written += DB::connection('tm2mst')->table('prod_product')
                    ->where('old_item_code', $oldCode)
                    ->update($row);
            }
        });

        return $written;
    }

    // goods_belongto berisi nama panggilan customer ("SJJ", "TEMPRINA"), bukan
    // kode -- satu-satunya kolom V2 yang sepadan adalah cust_customer.nickname
    // (355 terisi, 0 ambigu). customer_code/alias_code dicek dan 0 yang cocok.
    protected function customerNicknameMap(): array
    {
        $map = [];
        $ambiguous = [];

        $rows = DB::connection('tm2mst')->table('cust_customer')
            ->whereNotNull('nickname')->where('nickname', '<>', '')
            ->get(['id_customer', 'nickname']);

        foreach ($rows as $row) {
            $key = mb_strtoupper(trim($row->nickname));
            if (isset($map[$key])) {
                $ambiguous[$key] = true;
            }
            $map[$key] = (int) $row->id_customer;
        }

        // Nickname yang dipakai >1 customer tidak boleh dipakai menebak -- lebih
        // baik kosong daripada produk nempel ke customer yang salah.
        foreach (array_keys($ambiguous) as $key) {
            unset($map[$key]);
        }

        return $map;
    }

    protected function categoryNameMap(): array
    {
        return DB::connection('tm2mst')->table('prod_category')
            ->pluck('id_category', 'category_name')
            ->all();
    }

    protected function classify(string $purpose): ?string
    {
        $words = preg_split('/[^A-Z0-9]+/', mb_strtoupper($purpose), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach (self::CATEGORY_RULES as $category => $keywords) {
            if (array_intersect($words, $keywords)) {
                return $category;
            }
        }

        return null;
    }

    protected function blank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
