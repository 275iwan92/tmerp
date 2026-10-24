<?php

/*
 * REMARK
 * Tujuan       : Logika murni pencocokan NAMA pengguna sistem lama (tm2old.tm3sec_users.name) dengan sec_user.name (ddtsys), tanpa database. Dipakai perintah security:import-old-passwords.
 * Sumber hulu  : Daftar [id => nama] sumber dan [id => nama] target dari perintah.
 * Input        : Dua array nama.
 * Output       : exact [targetId => sourceId] (persis setelah normalisasi huruf besar/kecil + spasi, tunggal dua arah), doubtful [targetId => [kandidat sourceId, alasan]], none [targetId].
 * Dipakai oleh : App\Console\Commands\ImportOldPasswords, tests/Unit/LegacyNameMatcherTest.php
 * Status       : Aktif, SEMENTARA UAT (dibuang bersama perintah setelah UAT; lihat docs/backlog09_platform_facility.md butir pasca-UAT).
 */

namespace App\Services\Security;

class LegacyNameMatcher
{
    // Hanya huruf besar/kecil, spasi ganda dan spasi tepi (keputusan Jo 2026-10-24). Tanda baca dan gelar TIDAK dibuang.
    public static function normalize(?string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $name)));
    }

    // Kunci longgar HANYA untuk mencari yang meragukan, tidak pernah dipakai untuk menerapkan.
    private static function tokens(string $norm): array
    {
        $t = preg_split('/[^\p{L}\p{N}]+/u', $norm, -1, PREG_SPLIT_NO_EMPTY);
        sort($t);

        return $t;
    }

    private static function doubt(string $a, string $b): ?string
    {
        $ta = self::tokens($a);
        $tb = self::tokens($b);
        $la = implode('', $ta);
        $lb = implode('', $tb);
        if ($ta === $tb) {
            return 'kata sama, beda urutan atau tanda baca';
        }
        if ($la !== '' && preg_replace('/[^\p{L}\p{N}]/u', '', $a) === preg_replace('/[^\p{L}\p{N}]/u', '', $b)) {
            return 'beda tanda baca atau spasi';
        }
        [$small, $big] = count($ta) <= count($tb) ? [$ta, $tb] : [$tb, $ta];
        if (count($small) >= 2 && ! array_diff($small, $big)) {
            return 'salah satu lebih panjang (gelar, singkatan atau nama tambahan)';
        }
        if (strlen($la) >= 8 && strlen($lb) >= 8 && levenshtein($la, $lb) <= 2) {
            return 'ejaan mirip (selisih 1-2 huruf)';
        }

        return null;
    }

    /**
     * @param  array<int|string,string>  $targets  id => nama
     * @param  array<int|string,string>  $sources  id => nama
     * @return array{exact: array, doubtful: array, none: array}
     */
    public static function match(array $targets, array $sources): array
    {
        $tg = $sg = [];
        foreach ($targets as $id => $n) {
            $tg[self::normalize($n)][] = $id;
        }
        foreach ($sources as $id => $n) {
            $sg[self::normalize($n)][] = $id;
        }

        $exact = $doubtful = $none = [];
        $usedSources = [];
        foreach ($tg as $key => $tids) {
            if ($key === '') {
                foreach ($tids as $tid) {
                    $none[] = $tid;
                }

                continue;
            }
            $sids = $sg[$key] ?? [];
            if (count($tids) === 1 && count($sids) === 1) {
                $exact[$tids[0]] = $sids[0];
                $usedSources[$sids[0]] = true;
            } elseif ($sids) {
                foreach ($tids as $tid) {
                    $doubtful[$tid] = [$sids, count($tids) > 1 ? 'nama kembar di target' : 'nama kembar di sumber'];
                }
            }
        }

        $free = array_filter($sources, fn ($n, $id) => ! isset($usedSources[$id]) && self::normalize($n) !== '', ARRAY_FILTER_USE_BOTH);
        foreach ($targets as $tid => $tname) {
            if (isset($exact[$tid]) || isset($doubtful[$tid])) {
                continue;
            }
            $cand = [];
            $why = [];
            foreach ($free as $sid => $sname) {
                if ($r = self::doubt(self::normalize($tname), self::normalize($sname))) {
                    $cand[] = $sid;
                    $why[$r] = true;
                }
            }
            if ($cand) {
                $doubtful[$tid] = [$cand, implode('; ', array_keys($why))];
            } else {
                $none[] = $tid;
            }
        }

        return compact('exact', 'doubtful', 'none');
    }
}
