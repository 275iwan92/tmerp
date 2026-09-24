<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Database dummy ddt* = salinan PENUH tm2* (skema + data + view), keputusan user 2026-09-24.
// Hanya MEMBACA tm2*. VIEW yang menyebut database lain ditulis ulang tm2xxx -> ddtxxx supaya view
// dummy tidak membaca data real; tm2old tidak diduplikasi (dipakai bersama, baca saja).
// ddtsys TIDAK termasuk di sini: isinya sudah milik ddtsys (akun, status menu v2) -> lihat ddt:copy-sys.
//   php artisan ddt:copy                 -> semua (kecuali sys), tabel yang sudah ada dilewati
//   php artisan ddt:copy --db=mst,sdi    -> sebagian
//   php artisan ddt:copy --fresh         -> buang database ddt tujuan lalu salin ulang
class DdtCopy extends Command
{
    protected $signature = 'ddt:copy {--db= : mst,sdi,pur,inv,prd,acc,hrm,hrr,pyr (dipisah koma)} {--fresh : buang ddt tujuan dan salin ulang}';

    protected $description = 'Salin penuh tm2* (skema, data, view) ke database dummy ddt*';

    public const SUFFIXES = ['mst', 'sdi', 'pur', 'inv', 'prd', 'acc', 'hrm', 'hrr', 'pyr'];

    public function handle(): int
    {
        $db = DB::connection('tm2sys');
        $list = $this->option('db') ? array_map('trim', explode(',', $this->option('db'))) : self::SUFFIXES;
        if ($bad = array_diff($list, self::SUFFIXES)) {
            $this->error('Tidak dikenal: '.implode(',', $bad).' (ddtsys lewat ddt:copy-sys)');

            return self::FAILURE;
        }

        $db->statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($list as $s) {
                $src = "tm2$s";
                $dst = "ddt$s";
                if ($this->option('fresh')) {
                    $db->statement("DROP DATABASE IF EXISTS `$dst`");
                }
                $db->statement("CREATE DATABASE IF NOT EXISTS `$dst` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $have = array_map(fn ($r) => array_values((array) $r)[0], $db->select("SHOW TABLES FROM `$dst`"));

                $tables = 0;
                $rows = 0;
                foreach ($db->select("SHOW FULL TABLES FROM `$src` WHERE Table_type = 'BASE TABLE'") as $r) {
                    $t = array_values((array) $r)[0];
                    if (in_array($t, $have, true)) {
                        continue;
                    }
                    $create = array_values((array) $db->selectOne("SHOW CREATE TABLE `$src`.`$t`"))[1];
                    $db->statement(preg_replace('/^CREATE TABLE `/', "CREATE TABLE `$dst`.`", $create));
                    // Kolom GENERATED tidak boleh diisi INSERT ... SELECT *.
                    $cols = collect($db->select("SELECT COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND EXTRA NOT LIKE '%GENERATED%' ORDER BY ORDINAL_POSITION", [$src, $t]))
                        ->map(fn ($c) => "`{$c->c}`")->implode(',');
                    $rows += $db->affectingStatement("INSERT INTO `$dst`.`$t` ($cols) SELECT $cols FROM `$src`.`$t`");
                    $tables++;
                }

                // View terakhir: bisa bergantung pada view lain -> ulangi sampai tidak ada kemajuan.
                $pending = collect($db->select("SELECT TABLE_NAME n FROM information_schema.VIEWS WHERE TABLE_SCHEMA = ?", [$src]))->pluck('n')->diff($have)->values()->all();
                $views = 0;
                while ($pending) {
                    $left = [];
                    foreach ($pending as $v) {
                        $sql = array_values((array) $db->selectOne("SHOW CREATE VIEW `$src`.`$v`"))[1];
                        $sql = preg_replace('/ DEFINER=`[^`]+`@`[^`]+`/', '', $sql);
                        $sql = preg_replace('/`tm2(mst|sdi|pur|inv|prd|acc|hrm|hrr|pyr|sys)`\./', '`ddt$1`.', $sql);
                        $sql = preg_replace('/VIEW `'.preg_quote($v, '/').'`/', "VIEW `$dst`.`$v`", $sql, 1);
                        try {
                            $db->statement($sql);
                            $views++;
                        } catch (\Throwable $e) {
                            $left[] = $v;
                        }
                    }
                    if (count($left) === count($pending)) {
                        $this->error("$dst: view gagal dibuat: ".implode(', ', $left));
                        break;
                    }
                    $pending = $left;
                }
                $this->line(sprintf('  %-7s -> %-7s tabel baru %3d  baris %8d  view %d', $src, $dst, $tables, $rows, $views));
            }
        } finally {
            $db->statement('SET FOREIGN_KEY_CHECKS=1');
        }

        return self::SUCCESS;
    }
}
