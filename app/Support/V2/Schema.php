<?php

namespace App\Support\V2;

// Data Dictionary (khusus developer): struktur MySQL tmerp dari resources/data/schema.json
// (dibuat `php artisan schema:dictionary` di tmerp, otomatis setelah migrate/rollback).
// Dipakai: tooltip "nanti ke tabel apa, DB mana", cek src mockup, dan analisis kolom ganda (optimasi/normalisasi).
final class Schema
{
    // Kolom baku yang memang ada di banyak tabel -> tidak dianggap ganda.
    private const STANDARD = ['id', 'created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_at', 'deleted_by', 'activate', 'approved_by', 'approved_at',
        'cancel_reason', 'data_origin', 'sort_order', 'remember_token', 'stat_confirm', 'stat_cancel', 'stat_close', 'note', 'notes', 'remarks', 'description',
        // atribut umum milik entitasnya sendiri (bukan salinan data)
        'name', 'code', 'label', 'title', 'status', 'type', 'value', 'level', 'color', 'icon'];

    // Nama berbeda yang artinya sama -> dinormalkan ke satu kata.
    private const SYNONYM = ['quantity' => 'qty', 'jumlah' => 'qty', 'is_active' => 'activate', 'active' => 'activate', 'aktif' => 'activate',
        'remarks' => 'note', 'notes' => 'note', 'keterangan' => 'note', 'catatan' => 'note', 'harga' => 'price', 'nama' => 'name', 'telp' => 'phone',
        'telepon' => 'phone', 'phone_no' => 'phone', 'hp' => 'phone', 'alamat' => 'address', 'tgl' => 'date', 'tanggal' => 'date', 'desc' => 'description',
        'deskripsi' => 'description', 'satuan' => 'uom', 'unit' => 'uom', 'is_confirmed' => 'stat_confirm', 'is_cancelled' => 'stat_cancel',
        'create_by' => 'created_by', 'update_by' => 'updated_by', 'create_at' => 'created_at', 'update_at' => 'updated_at', 'createdby' => 'created_by', 'updatedby' => 'updated_by'];

    // Nama kolom yang DIKUNCI CLAUDE.md §3 (DEC-FIELDNAME-STATUS dll): ditulis lain = pelanggaran konvensi.
    private const CONVENTION = ['created_by', 'updated_by', 'created_at', 'updated_at', 'activate', 'stat_confirm', 'stat_cancel', 'stat_close'];

    private const LEGACY = ['tm2old'];

    private static ?array $data = null;

    public static function all(): array
    {
        return self::$data ??= json_decode(file_get_contents(resource_path('data/v2/schema.json')), true);
    }

    public static function generated(): string
    {
        return self::all()['_generated'] ?? '';
    }

    public static function databases(): array
    {
        return array_values(array_filter(array_keys(self::all()), fn ($k) => ! str_starts_with($k, '_')));
    }

    // "tm2sdi.iqp_inquiry.qty" | "tm2sdi.iqp_inquiry" -> info kolom/tabel, null bila tidak ada.
    public static function find(string $src): ?array
    {
        [$db, $table, $col] = array_pad(explode('.', $src, 3), 3, null);
        $t = self::all()[$db][$table] ?? null;
        if (! $t) {
            return null;
        }
        if ($col === null) {
            return ['table' => $t];
        }
        $c = $t['columns'][$col] ?? null;

        return $c ? ['type' => $c[0], 'null' => $c[1], 'key' => $c[2], 'comment' => $c[3], 'fk' => $t['fk'][$col] ?? null] : null;
    }

    // Satu baris = satu kolom.
    public static function columns(): array
    {
        $rows = [];
        foreach (self::databases() as $db) {
            foreach (self::all()[$db] as $table => $t) {
                foreach ($t['columns'] as $c => [$type, $null, $key, $comment]) {
                    $rows[] = ['id' => "$db.$table.$c", 'db' => $db, 'table' => $table, 'col' => $c, 'type' => $type, 'null' => $null ? 'ya' : '',
                        'key' => $key, 'fk' => $t['fk'][$c] ?? '', 'comment' => $comment, 'kind' => $t['type']];
                }
            }
        }

        return $rows;
    }

    // Satu baris = satu tabel, kolom-kolomnya = detail.
    public static function tables(): array
    {
        $rows = [];
        foreach (self::databases() as $db) {
            foreach (self::all()[$db] as $name => $t) {
                $cols = [];
                foreach ($t['columns'] as $c => [$type, $null, $key, $comment]) {
                    $cols[] = ['col' => $c, 'type' => $type, 'null' => $null ? 'ya' : '', 'key' => $key, 'fk' => $t['fk'][$c] ?? '', 'comment' => $comment];
                }
                $rows[] = ['id' => $db.'.'.$name, 'db' => $db, 'table' => $name, 'kind' => $t['type'], 'n' => count($cols), 'nfk' => count($t['fk']), 'comment' => $t['comment'], 'cols' => $cols];
            }
        }

        return $rows;
    }

    private static function norm(string $c): string
    {
        $c = strtolower($c);

        return self::SYNONYM[$c] ?? $c;
    }

    // Kandidat kolom ganda (tabel biasa, di luar DB legacy, tanpa PK/FK/kolom baku).
    public static function duplicates(): array
    {
        $groups = [];
        $nonstd = [];
        foreach (self::databases() as $db) {
            if (in_array($db, self::LEGACY, true)) {
                continue;
            }
            foreach (self::all()[$db] as $table => $t) {
                if ($t['type'] !== 'table') {
                    continue;
                }
                foreach ($t['columns'] as $c => [$type, , $key]) {
                    if ($key === 'PRI' || isset($t['fk'][$c]) || str_starts_with($c, 'id_')) {
                        continue;
                    }
                    $n = self::norm($c);
                    if (in_array($n, self::STANDARD, true)) {
                        // Kolom baku tapi ditulis dengan nama lain (create_by, is_active) -> pelanggaran konvensi nama.
                        if ($n !== strtolower($c) && in_array($n, self::CONVENTION, true)) {
                            $nonstd[$c.' -> '.$n][] = ['db' => $db, 'table' => $table, 'col' => $c, 'type' => $type];
                        }

                        continue;
                    }
                    $groups[$n][] = ['db' => $db, 'table' => $table, 'col' => $c, 'type' => $type];
                }
            }
        }
        $rows = [];
        foreach ($groups as $n => $list) {
            if (count($list) < 2) {
                continue;
            }
            $names = array_values(array_unique(array_column($list, 'col')));
            $types = array_values(array_unique(array_column($list, 'type')));
            $rows[] = ['id' => $n, 'name' => $n, 'count' => count($list), 'names' => implode(', ', $names), 'types' => implode(', ', $types),
                'flag' => count($names) > 1 ? 'Sinonim' : (count($types) > 1 ? 'Tipe beda' : 'Nama sama'),
                'where' => array_map(fn ($x) => $x + ['at' => $x['db'].'.'.$x['table']], $list)];
        }
        foreach ($nonstd as $n => $list) {
            $rows[] = ['id' => 'x:'.$n, 'name' => $n, 'count' => count($list), 'names' => explode(' -> ', $n)[0], 'types' => implode(', ', array_unique(array_column($list, 'type'))),
                'flag' => 'Nama tidak baku', 'where' => array_map(fn ($x) => $x + ['at' => $x['db'].'.'.$x['table']], $list)];
        }
        $rank = ['Nama tidak baku' => 3, 'Sinonim' => 2, 'Tipe beda' => 1, 'Nama sama' => 0];
        usort($rows, fn ($a, $b) => [$rank[$b['flag']], $b['count']] <=> [$rank[$a['flag']], $a['count']]);

        return $rows;
    }

    // Salinan teks master di tabel lain: <x>_name / <x>_code di tabel yang juga punya <x>_id / id_<x>.
    public static function copies(): array
    {
        $rows = [];
        foreach (self::databases() as $db) {
            if (in_array($db, self::LEGACY, true)) {
                continue;
            }
            foreach (self::all()[$db] as $table => $t) {
                if ($t['type'] !== 'table') {
                    continue;
                }
                $cols = array_keys($t['columns']);
                foreach ($cols as $c) {
                    if (! preg_match('/^(.+)_(name|code|label|title)$/', $c, $m)) {
                        continue;
                    }
                    $ref = array_values(array_intersect([$m[1].'_id', 'id_'.$m[1]], $cols));
                    // Kode/nama milik tabelnya sendiri (samping PK-nya) bukan salinan.
                    if ($ref && ($t['columns'][$ref[0]][2] ?? '') !== 'PRI') {
                        $rows[] = ['id' => "$db.$table.$c", 'db' => $db, 'table' => $table, 'col' => $c, 'ref' => $ref[0], 'fk' => $t['fk'][$ref[0]] ?? '(logis / lintas DB)'];
                    }
                }
            }
        }

        return $rows;
    }

    public static function changes(): array
    {
        $f = resource_path('data/v2/schema-changes.json');
        $rows = [];
        foreach (is_file($f) ? json_decode(file_get_contents($f), true) : [] as $batch) {
            foreach ($batch['items'] as $i => $it) {
                $rows[] = $it + ['id' => $batch['at'].'#'.$i, 'at' => $batch['at']];
            }
        }

        return $rows;
    }

    public static function stats(): array
    {
        $cols = self::columns();

        return ['db' => count(self::databases()), 'tables' => count(self::tables()), 'columns' => count($cols),
            'dup' => count(self::duplicates()), 'copies' => count(self::copies()), 'generated' => self::generated()];
    }
}
