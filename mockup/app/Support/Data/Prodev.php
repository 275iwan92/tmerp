<?php

namespace App\Support\Data;

// Data layar ProDev: ekspor baca-saja dari komponen tmerp asli (resources/data/prodev.json, transaksi di-rollback).
// list = daftar part (prodev-list), work[id] = data workbench per part (jobs + hasil SpecEngine contoh).
final class Prodev
{
    private static function all(): array
    {
        static $d;

        return $d ??= json_decode(file_get_contents(resource_path('data/prodev.json')), true);
    }

    public static function parts(): array
    {
        return self::all()['list'];
    }

    // Part yang punya data workbench; "Kirim ke ProDev" dari Inquiry mockup (nama part baru) diarahkan ke part asal Inquiry pertama.
    public static function resolve(string $part): ?int
    {
        $work = self::all()['work'];
        if (ctype_digit($part) && isset($work[$part])) {
            return (int) $part;
        }
        $origin = collect(self::parts())->firstWhere(fn ($p) => $p['origin'] === 'INQUIRY' && ! empty($work[$p['id']]['jobs']));

        return $origin['id'] ?? null;
    }

    public static function workbench(int $id, string $backUrl, ?string $costingUrl): array
    {
        $all = self::all();
        $w = $all['work'][$id] ?? ['jobs' => [], 'jobIssue' => 'Part tidak ada di data contoh.'];

        return ($all['shared'] ?? []) + $w + ['initialJob' => 0, 'jobIssue' => $w['jobIssue'] ?? null, 'backUrl' => $backUrl,
            'costingUrl' => $costingUrl, 'materials' => $all['materials']];
    }
}
