<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Data Dictionary untuk mockup.test (khusus developer): STRUKTUR semua database tm2* dari information_schema
// (tidak membaca data) -> mockup/resources/data/schema.json + riwayat perubahan schema-changes.json.
// Otomatis jalan setelah `php artisan migrate` (AppServiceProvider), atau manual: php artisan schema:dictionary
class SchemaDictionary extends Command
{
    protected $signature = 'schema:dictionary';

    protected $description = 'Ekspor struktur database tm2* ke Data Dictionary mockup.test (+ catat perubahan)';

    public function handle(): int
    {
        $dir = base_path('mockup/resources/data');
        if (! is_dir(base_path('mockup'))) {
            $this->warn('Folder mockup/ tidak ada — dilewati.');

            return self::SUCCESS;
        }
        @mkdir($dir, 0777, true);

        $db = DB::connection('tm2sys');
        $out = ['_generated' => now()->toDateTimeString(), '_source' => 'information_schema MySQL lokal (struktur saja)'];
        foreach ($db->select("SELECT SCHEMA_NAME s FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE 'tm2%' ORDER BY 1") as $row) {
            $s = $row->s;
            $out[$s] = [];
            $cols = collect($db->select('SELECT TABLE_NAME t, COLUMN_NAME c, COLUMN_TYPE y, IS_NULLABLE n, COLUMN_KEY k, COLUMN_COMMENT m FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION', [$s]))->groupBy('t');
            $fks = collect($db->select('SELECT TABLE_NAME t, COLUMN_NAME c, REFERENCED_TABLE_NAME rt, REFERENCED_COLUMN_NAME rc FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL', [$s]))->groupBy('t');
            foreach ($db->select('SELECT TABLE_NAME t, TABLE_TYPE k, TABLE_COMMENT c FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY 1', [$s]) as $t) {
                $out[$s][$t->t] = [
                    'type' => $t->k === 'VIEW' ? 'view' : 'table',
                    'comment' => $t->c,
                    'columns' => collect($cols[$t->t] ?? [])->mapWithKeys(fn ($c) => [$c->c => [$c->y, $c->n === 'YES', $c->k, $c->m]])->all(),
                    'fk' => collect($fks[$t->t] ?? [])->mapWithKeys(fn ($f) => [$f->c => $f->rt.'.'.$f->rc])->all(),
                ];
            }
        }

        $file = $dir.'/schema.json';
        $prev = is_file($file) ? json_decode(file_get_contents($file), true) : null;
        file_put_contents($file, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $changes = $prev ? $this->diff($prev, $out) : [];
        if ($changes) {
            $logFile = $dir.'/schema-changes.json';
            $log = is_file($logFile) ? json_decode(file_get_contents($logFile), true) : [];
            array_unshift($log, ['at' => $out['_generated'], 'items' => $changes]);
            file_put_contents($logFile, json_encode(array_slice($log, 0, 100), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }
        $tables = collect($out)->except(['_generated', '_source'])->sum(fn ($t) => count($t));
        $this->info("Data Dictionary: {$tables} tabel/view, ".count($changes).' perubahan sejak ekspor sebelumnya.');

        return self::SUCCESS;
    }

    // Tabel/kolom ditambah, dihapus, atau berubah tipe.
    private function diff(array $a, array $b): array
    {
        $items = [];
        $dbs = array_unique(array_merge(array_keys($a), array_keys($b)));
        foreach ($dbs as $s) {
            if (str_starts_with($s, '_')) {
                continue;
            }
            foreach (array_unique(array_merge(array_keys($a[$s] ?? []), array_keys($b[$s] ?? []))) as $t) {
                $old = $a[$s][$t] ?? null;
                $new = $b[$s][$t] ?? null;
                if (! $old || ! $new) {
                    $items[] = ['kind' => $old ? 'tabel dihapus' : 'tabel baru', 'db' => $s, 'table' => $t, 'column' => '', 'detail' => ''];

                    continue;
                }
                foreach (array_unique(array_merge(array_keys($old['columns']), array_keys($new['columns']))) as $c) {
                    $oc = $old['columns'][$c] ?? null;
                    $nc = $new['columns'][$c] ?? null;
                    if (! $oc || ! $nc) {
                        $items[] = ['kind' => $oc ? 'kolom dihapus' : 'kolom baru', 'db' => $s, 'table' => $t, 'column' => $c, 'detail' => ($nc ?? $oc)[0]];
                    } elseif ($oc[0] !== $nc[0] || $oc[1] !== $nc[1]) {
                        $items[] = ['kind' => 'tipe berubah', 'db' => $s, 'table' => $t, 'column' => $c, 'detail' => $oc[0].($oc[1] ? ' null' : '').' -> '.$nc[0].($nc[1] ? ' null' : '')];
                    }
                }
            }
        }

        return $items;
    }
}
