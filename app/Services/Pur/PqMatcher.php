<?php

namespace App\Services\Pur;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Cocokkan teks dari file penawaran ke master: nama supplier -> sup_supplier, teks baris -> baris PR acuan (utama)
// atau Master Bahan Baku. "Engine suggests, officer confirms": skor < SURE ditandai kuning, officer wajib memilih.
final class PqMatcher
{
    public const SURE = 80;

    private const MIN = 55;

    /** @return array{0: ?int, 1: int} [id_supplier, skor] */
    public static function supplier(?string $name): array
    {
        $want = self::norm($name, true);
        if ($want === '') {
            return [null, 0];
        }
        $best = [null, 0];
        foreach (DB::connection('tm2mst')->table('sup_supplier')->where('activate', 1)->pluck('supplier_name', 'id_supplier') as $id => $n) {
            $s = self::score($want, self::norm($n, true));
            if ($s > $best[1]) {
                $best = [(int) $id, $s];
            }
        }

        return $best[1] >= self::MIN ? $best : [null, $best[1]];
    }

    /** @return array{pr_line_id: ?int, item_id: ?int, pct: int} */
    public static function line(string $text, Collection $prLines): array
    {
        $want = self::norm($text);
        $best = ['pr_line_id' => null, 'item_id' => null, 'pct' => 0];
        foreach ($prLines as $l) {
            $s = self::score($want, self::norm($l->i_description));
            if ($s > $best['pct']) {
                $best = ['pr_line_id' => (int) $l->pr_line_id, 'item_id' => $l->item_id ? (int) $l->item_id : null, 'pct' => $s];
            }
        }
        if ($best['pct'] >= self::MIN) {
            return $best;
        }

        // Bukan baris PR: cari di Master Bahan Baku, disaring dulu pakai 2 kata terpanjang supaya tidak menilai 11 rb baris.
        $words = collect(explode(' ', $want))->filter(fn ($w) => preg_match('/^[a-z]{3,}$/', $w))->sortByDesc(fn ($w) => strlen($w))->take(2);
        if ($words->isEmpty()) {
            return ['pr_line_id' => null, 'item_id' => null, 'pct' => $best['pct']];
        }
        $q = DB::connection('tm2mst')->table('mat_raw')->where('activate', 1)->where('stat_confirm', 1);
        $words->each(fn ($w) => $q->where('material_name', 'like', "%{$w}%"));
        $mat = ['pr_line_id' => null, 'item_id' => null, 'pct' => 0];
        foreach ($q->limit(300)->pluck('material_name', 'material_id') as $id => $n) {
            $s = self::score($want, self::norm($n));
            if ($s > $mat['pct']) {
                $mat = ['pr_line_id' => null, 'item_id' => (int) $id, 'pct' => $s];
            }
        }

        return $mat['pct'] >= self::MIN ? $mat : ['pr_line_id' => null, 'item_id' => null, 'pct' => max($best['pct'], $mat['pct'])];
    }

    // Rata-rata kata yang sama (angka ukuran/gramatur ikut dihitung) dan kemiripan huruf.
    public static function score(string $a, string $b): int
    {
        if ($a === '' || $b === '') {
            return 0;
        }
        if ($a === $b) {
            return 100;
        }
        $ta = array_unique(explode(' ', $a));
        $tb = array_unique(explode(' ', $b));
        $overlap = count(array_intersect($ta, $tb)) / max(count($ta), count($tb)) * 100;
        similar_text($a, $b, $pct);

        return (int) round(($overlap + $pct) / 2);
    }

    private static function norm(?string $s, bool $company = false): string
    {
        $s = mb_strtolower((string) $s);
        $s = preg_replace('/(\d)\s*[x×*]\s*(\d)/u', '$1x$2', $s);
        $s = preg_replace('/(\d)\s*(gr|gsm|g)\b/', '$1', $s);
        $s = preg_replace('/[^a-z0-9x ]+/', ' ', $s);
        if ($company) {
            $s = preg_replace('/\b(pt|cv|ud|tbk|toko|tk|persero)\b/', ' ', $s);
        }

        return trim(preg_replace('/\s+/', ' ', $s));
    }
}
