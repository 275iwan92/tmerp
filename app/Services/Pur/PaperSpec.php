<?php

/*
 * REMARK
 * Tujuan       : Membaca spesifikasi kertas dari bahan: gramasi, ukuran, kunci substance, kunci flute; dipakai bersama oleh semua engine harga beli kertas.
 * Sumber hulu  : Master Raw Material (gram_weight, cm_length, cm_width); bila kosong dibaca dari nama bahan (mis. "DUPLEX 350 GR 71,5 X 85", "SF EF M125/K125 865 X 560 MM").
 * Input        : MatRaw atau teks nama bahan.
 * Output       : Gramasi, ukuran cm atau m, kunci substance (mis. K150M125K125), kunci flute (EF, BF, BCF), jenis dinding (SW / DW).
 * Dipakai oleh : SingleFacePriceEngine, DuplexPriceEngine, CorrugatedSheetPriceEngine.
 * Status       : Aktif; murni baca, tidak menulis apa pun.
 */

namespace App\Services\Pur;

use App\Models\Master\MatRaw;

final class PaperSpec
{
    // Gramasi: kolom master bila terisi, kalau tidak angka sebelum GR / GSM di nama.
    public static function gsm(MatRaw $m): float
    {
        if ((float) $m->gram_weight > 0) {
            return (float) $m->gram_weight;
        }

        return preg_match('/(\d{2,3})\s*(?:GR|GSM)\b/i', (string) $m->material_name, $x) ? (float) $x[1] : 0.0;
    }

    // Ukuran [panjang, lebar] dalam cm: kolom master bila keduanya terisi, kalau tidak dari nama.
    public static function sizeCm(MatRaw $m): ?array
    {
        if ((float) $m->cm_length > 0 && (float) $m->cm_width > 0) {
            return [(float) $m->cm_length, (float) $m->cm_width];
        }
        $s = self::sizeM((string) $m->material_name);

        return $s ? [$s[0] * 100, $s[1] * 100] : null;
    }

    // Ukuran dari nama, "A x B" dengan satuan CM atau MM; tanpa satuan: kedua sisi <= 250 dianggap CM, selain itu MM. Hasil dalam meter, urutan seperti di nama.
    public static function sizeM(string $text): ?array
    {
        if (! preg_match_all('/(\d+(?:[.,]\d+)?)\s*[xX]\s*(\d+(?:[.,]\d+)?)\s*(MM|CM)?/i', $text, $all, PREG_SET_ORDER)) {
            return null;
        }
        $s = end($all);
        $a = (float) str_replace(',', '.', $s[1]);
        $b = (float) str_replace(',', '.', $s[2]);
        $unit = strtoupper($s[3] ?? '') ?: (max($a, $b) <= 250 ? 'CM' : 'MM');
        $div = $unit === 'MM' ? 1000 : 100;

        return $a > 0 && $b > 0 ? [$a / $div, $b / $div] : null;
    }

    // Kunci substance berurutan, mis. "SF M 125 / K 125", "S FACE 125 M / 125 K", dan "M125/K125" semuanya jadi "M125K125"; "M125X3" tetap "M125X3".
    public static function substanceKey(string $text): string
    {
        preg_match_all('/\b(WK|WL|KL|K|M)\s*(\d{2,3})(X\d)?\b|\b(\d{2,3})\s*(WK|WL|KL|K|M)\b/i', strtoupper($text), $t, PREG_SET_ORDER);

        return implode('', array_map(fn ($x) => ($x[1] !== '' ? $x[1].$x[2].($x[3] ?? '') : $x[5].$x[4]), $t));
    }

    // Flute: E/F, EF, B/F, BF, C/F, BC/F, B/CF dan sejenisnya jadi "EF", "BF", "CF", "BCF".
    public static function fluteKey(string $text): string
    {
        return preg_match('/\b(B\s*\/\s*C|BC|B|C|E)\s*\/?\s*F\b/i', strtoupper($text), $x) ? preg_replace('/[\s\/]/', '', strtoupper($x[1])).'F' : '';
    }

    // Dinding gelombang: DW bila nama memuat SDW, DW, DOUBLE, atau kelipatan flute (X3); selain itu SW.
    public static function wall(string $text): string
    {
        return preg_match('/\bSDW\b|\bDW\b|DOUBLE|X\s*3\b/i', $text) ? 'DW' : 'SW';
    }
}
