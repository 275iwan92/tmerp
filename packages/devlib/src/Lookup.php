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
// Data besar (ribuan baris, mis. product / wilayah): cari ke server, baris tidak ditanam di halaman.
//   search  : fn (string $q, array $ctx): iterable baris   (dipanggil route devlib.lookup, min. 2 huruf, maks. 50 baris)
//   find    : fn ($value): ?array baris   (teks nilai yang sudah tersimpan)
//   ctx = parameter tambahan dari field (prop :params), mis. customer_id supaya product customer itu didahulukan.
final class Lookup
{
    private static array $defs = [];

    public static function define(string $name, array $def): void
    {
        self::$defs[$name] = $def;
    }

    private static function def(string $name): array
    {
        return self::$defs[$name] ?? config('devlib.lookups.'.$name, []);
    }

    public static function get(string $name): array
    {
        $def = self::def($name);
        $remote = isset($def['search']);
        $rows = $remote ? [] : ($def['rows'] ?? []);
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
            'remote' => $remote,
        ];
    }

    public static function search(string $name, string $q, array $ctx = []): array
    {
        $def = self::def($name);
        abort_unless(isset($def['search']), 404);
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        return array_slice(array_values(array_map(fn ($r) => (array) $r, collect(($def['search'])($q, $ctx))->all())), 0, 50);
    }

    public static function find(string $name, mixed $value): ?array
    {
        $def = self::def($name);
        if ($value === null || $value === '' || ! isset($def['find'])) {
            return null;
        }
        $r = ($def['find'])($value);

        return $r ? (array) $r : null;
    }
}
