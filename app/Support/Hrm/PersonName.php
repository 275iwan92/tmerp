<?php

namespace App\Support\Hrm;

// Pencocokan nama karyawan antar-sumber (Excel HR, file gaji, data legacy)
// yang ditulis beda-beda: "M. RAFLY" ~ "MUHAMMAD RAFLY", "ENI HANINGTYAS" ~
// "ENI KURNIATI HANINGTYAS", "SUFRIATI" ~ "SUPRIATI".
final class PersonName
{
    public static function norm(?string $s): string
    {
        $s = strtoupper(str_replace('.', ' ', (string) $s));

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z ]/', '', $s)));
    }

    public static function words(?string $s): int
    {
        $n = self::norm($s);

        return $n === '' ? 0 : count(explode(' ', $n));
    }

    public static function compatible(string $a, string $b): bool
    {
        [$a, $b] = [self::norm($a), self::norm($b)];
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b || str_contains(" $b ", " $a ") || str_contains(" $a ", " $b ")) {
            return true;
        }
        $ta = explode(' ', $a);
        $tb = explode(' ', $b);
        if (count($ta) === count($tb)) {
            $ok = true;
            foreach ($ta as $k => $t) {
                $u = $tb[$k];
                $ok = $ok && ($t === $u || (strlen($t) === 1 && str_starts_with($u, $t)) || (strlen($u) === 1 && str_starts_with($t, $u)));
            }
            if ($ok) {
                return true;
            }
        }
        // Kata-kata nama pendek muncul berurutan di nama panjang (boleh penggalan
        // awal kata): ENI HANINGTYAS ~ ENI KURNIATI HANINGTYAS, APRIL ~ APRILIANTI ...
        [$short, $long] = count($ta) <= count($tb) ? [$ta, $tb] : [$tb, $ta];
        $j = 0;
        foreach ($long as $t) {
            if ($j < count($short) && str_starts_with($t, $short[$j])) {
                $j++;
            }
        }
        if ($j === count($short)) {
            return true;
        }
        similar_text($a, $b, $pct);

        return $pct >= 80;
    }

    /**
     * Cari 1 kandidat untuk $name: sama persis dulu, lalu "compatible" -- hanya
     * bila hasilnya tunggal (nama ambigu tidak ditebak).
     *
     * @param  array<int, string>  $candidates  id => nama
     */
    public static function match(string $name, array $candidates): ?int
    {
        $key = self::norm($name);
        $exact = array_keys(array_filter($candidates, fn ($n) => self::norm($n) === $key));
        if (count($exact) === 1) {
            return $exact[0];
        }
        if (count($exact) > 1) {
            return null;
        }
        $loose = array_keys(array_filter($candidates, fn ($n) => self::compatible($name, $n)));

        return count($loose) === 1 ? $loose[0] : null;
    }
}
