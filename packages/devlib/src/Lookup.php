<?php

namespace Devlib;

// Lookup = definisi SEKALI per data ("supplier", "customer", "uom", ...) dipakai
// semua layar lewat <x-dl::field.lookup lookup="supplier" />. Mengurangi ketik:
// user memilih, dan field lain terisi otomatis (fill).
//   rows    : baris data (DevLib: config devlib.lookups; tmerp: query tabel/VIEW)
//   key     : kolom nilai tersimpan      label : kolom teks tampil
//   columns : kolom yang tampil & bisa dicari di daftar pilihan
//   fill    : nama field lain => kolom sumber (isi otomatis saat dipilih)
//   source  : asal data (ditampilkan Mode Developer)
final class Lookup
{
    private static array $defs = [];

    public static function define(string $name, array $def): void
    {
        self::$defs[$name] = $def;
    }

    public static function get(string $name): array
    {
        $def = self::$defs[$name] ?? config('devlib.lookups.'.$name, []);
        $rows = $def['rows'] ?? [];
        if ($rows instanceof \Closure) {
            $rows = $rows();
        }

        return [
            'rows' => array_values(array_map(fn ($r) => (array) $r, is_iterable($rows) ? (array) $rows : [])),
            'key' => $def['key'] ?? 'id',
            'label' => $def['label'] ?? 'name',
            'columns' => $def['columns'] ?? [($def['label'] ?? 'name') => 'Nama'],
            'fill' => $def['fill'] ?? [],
            'source' => $def['source'] ?? $name,
        ];
    }
}
