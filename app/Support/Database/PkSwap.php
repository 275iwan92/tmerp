<?php

namespace App\Support\Database;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\File;
use RuntimeException;

// Aturan PK (user 2026-09-30): ganti PK tabel lama menjadi id BIGINT auto number dengan metode tabel bayangan
// yang dipilih user supaya tidak ada yang rusak:
//   1. simpan semua FK yang menyentuh tabel ke file JSON (storage/app/pk-swap/<db>/<tabel>.json)
//   2. buat <tabel>__newpk = struktur sama + id BIGINT AUTO_INCREMENT PK; PK lama jadi UNIQUE
//   3. lepas FK, salin data, cocokkan jumlah baris + checksum isi (beda = batal, FK dipasang lagi)
//   4. RENAME atomik: <tabel> -> <tabel>__oldpk, <tabel>__newpk -> <tabel>
//   5. pasang FK kembali dari JSON. <tabel>__oldpk disimpan sebagai cadangan sampai user menyuruh hapus.
final class PkSwap
{
    public function __construct(private Connection $db) {}

    // $idColumn = nama PK baru (id_<entitas>); PK lama (tunggal/gabungan) menjadi UNIQUE, tanpa PK -> hanya tambah id.
    public function swap(string $table, string $idColumn): void
    {
        $schema = $this->db->getDatabaseName();
        $new = "{$table}__newpk";
        $old = "{$table}__oldpk";
        $oldPk = array_column($this->db->select("SELECT COLUMN_NAME c FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY ORDINAL_POSITION", [$schema, $table]), 'c');
        if ($oldPk === [$idColumn]) {
            return; // sudah ditukar (migrasi diulang sesudah gagal di tabel lain)
        }
        if ($this->exists($old)) {
            throw new RuntimeException("$schema.$old sudah ada -- tabel ini pernah ditukar, periksa dulu.");
        }
        $columns = array_column($this->db->select('SELECT COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', [$schema, $table]), 'c');
        if (in_array($idColumn, $columns, true)) {
            throw new RuntimeException("$schema.$table sudah punya kolom $idColumn.");
        }

        $fks = $this->foreignKeys($table);
        $dir = storage_path("app/pk-swap/$schema");
        File::ensureDirectoryExists($dir);
        File::put("$dir/$table.json", json_encode(['table' => $table, 'old_pk' => $oldPk, 'new_pk' => $idColumn, 'foreign_keys' => $fks, 'at' => now()->toDateTimeString()], JSON_PRETTY_PRINT));

        $this->db->statement("DROP TABLE IF EXISTS `$new`");
        $this->db->statement("CREATE TABLE `$new` LIKE `$table`");
        $alter = ($oldPk ? 'DROP PRIMARY KEY, ' : '')."ADD `$idColumn` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`$idColumn`)"
            .($oldPk ? ', ADD UNIQUE KEY `'.substr("{$table}_".implode('_', $oldPk).'_unique', 0, 64).'` (`'.implode('`, `', $oldPk).'`)' : '');
        $this->db->statement("ALTER TABLE `$new` $alter");

        $this->db->statement('SET FOREIGN_KEY_CHECKS=0');
        $swapped = false;
        try {
            foreach ($fks as $fk) {
                $this->db->statement("ALTER TABLE `{$fk['table']}` DROP FOREIGN KEY `{$fk['name']}`");
            }
            $cols = '`'.implode('`, `', $columns).'`';
            $order = $oldPk ? ' ORDER BY `'.implode('`, `', $oldPk).'`' : '';
            $this->db->statement("INSERT INTO `$new` ($cols) SELECT $cols FROM `$table`$order");
            [$a, $b] = [$this->fingerprint($table, $columns), $this->fingerprint($new, $columns)];
            if ($a !== $b) {
                throw new RuntimeException("$schema.$table: salinan tidak sama (lama {$a['n']} baris/{$a['sum']}, baru {$b['n']} baris/{$b['sum']}) -- dibatalkan.");
            }
            $this->db->statement("RENAME TABLE `$table` TO `$old`, `$new` TO `$table`");
            $swapped = true;
        } catch (\Throwable $e) {
            $this->db->statement("DROP TABLE IF EXISTS `$new`");
            throw $e;
        } finally {
            // Sukses maupun batal: FK dipasang lagi persis dari catatan (sesudah tukar, nama tabel sama -> menunjuk tabel baru).
            foreach ($fks as $fk) {
                $this->db->statement("ALTER TABLE `{$fk['table']}` ADD CONSTRAINT `{$fk['name']}` FOREIGN KEY (`".implode('`, `', $fk['cols']).'`) '
                    ."REFERENCES `{$fk['ref']}` (`".implode('`, `', $fk['refCols'])."`) ON DELETE {$fk['del']} ON UPDATE {$fk['upd']}");
            }
            $this->db->statement('SET FOREIGN_KEY_CHECKS=1');
        }
        if ($swapped && count($this->foreignKeys($table)) !== count($fks)) {
            throw new RuntimeException("$schema.$table: jumlah FK sesudah tukar tidak sama, cek storage/app/pk-swap/$schema/$table.json");
        }
    }

    private function exists(string $table): bool
    {
        return (bool) $this->db->selectOne('SELECT 1 x FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$this->db->getDatabaseName(), $table]);
    }

    // Jumlah baris + checksum seluruh kolom lama (NULL dibedakan dari string kosong).
    private function fingerprint(string $table, array $columns): array
    {
        $row = implode(", '|', ", array_map(fn ($c) => "COALESCE(CAST(`$c` AS CHAR), '<NULL>')", $columns));
        $r = $this->db->selectOne("SELECT COUNT(*) n, COALESCE(SUM(CRC32(CONCAT($row))), 0) sum FROM `$table`");

        return ['n' => (int) $r->n, 'sum' => (string) $r->sum];
    }

    // FK milik tabel ini + FK tabel lain yang merujuk ke tabel ini (FK lintas-DB tidak ada).
    private function foreignKeys(string $table): array
    {
        $rows = $this->db->select('SELECT k.CONSTRAINT_NAME name, k.TABLE_NAME t, k.COLUMN_NAME c, k.REFERENCED_TABLE_NAME rt, k.REFERENCED_COLUMN_NAME rc, r.DELETE_RULE del, r.UPDATE_RULE upd
            FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME
            WHERE k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL AND (k.TABLE_NAME = ? OR k.REFERENCED_TABLE_NAME = ?)
            ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION', [$this->db->getDatabaseName(), $table, $table]);
        $fks = [];
        foreach ($rows as $r) {
            $fks["$r->t.$r->name"] ??= ['table' => $r->t, 'name' => $r->name, 'ref' => $r->rt, 'del' => $r->del, 'upd' => $r->upd, 'cols' => [], 'refCols' => []];
            $fks["$r->t.$r->name"]['cols'][] = $r->c;
            $fks["$r->t.$r->name"]['refCols'][] = $r->rc;
        }

        return array_values($fks);
    }
}
