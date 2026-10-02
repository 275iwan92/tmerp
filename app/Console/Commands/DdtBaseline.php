<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SplFileObject;
use Symfony\Component\Process\Process;

// Baseline data dummy (user 2026-09-28): kondisi ddt* yang "benar" disimpan sebagai dump .sql, lalu tombol
// Reset data dummy mengembalikan ddt* persis ke kondisi itu. ddtsys (akun, menu, security) TIDAK ikut.
// File upload (artwork dll.) ada di disk public yang DIPAKAI BERSAMA tmerp.test -> hanya file yang dirujuk
// data dummy yang disalin & dikembalikan; folder tidak pernah dikosongkan.
// Dump tidak masuk git (~ratusan MB): storage/app/ddt-baseline.
//   php artisan ddt:baseline save --env=mockup
//   php artisan ddt:baseline restore --env=mockup
//   php artisan ddt:baseline check --env=mockup     (hanya membaca: struktur ddt* sekarang vs baseline)
// Drift (2026-10-02): dump berisi DROP TABLE + CREATE TABLE, sedangkan tabel `migrations` ada di ddtsys yang tidak ikut
// di-reset. Migration yang jalan SETELAH baseline disimpan -> restore memasang definisi tabel lama (kolom baru hilang)
// padahal migration tercatat sudah jalan -> layar gagal "Unknown column". Karena itu restore menolak bila struktur
// sudah berubah; jalan keluarnya simpan baseline baru, atau --allow-drift bila memang sengaja.
class DdtBaseline extends Command
{
    protected $signature = 'ddt:baseline {action : save|restore|check} {--force : tanpa konfirmasi}
        {--allow-drift : restore walau struktur ddt* sudah berubah sejak baseline (kolom baru ikut hilang)}';

    // Arti tiap jenis drift bagi Reset: [nama, akibat]. Tabel yang dibuat setelah baseline tidak ada di dump -> tidak disentuh.
    public const DRIFT = [
        'column_added' => ['New column', 'Reset drops these columns'],
        'column_removed' => ['Removed column', 'Reset brings these columns back'],
        'table_added' => ['New table', 'Not in the baseline: Reset leaves it as it is, rows included'],
        'table_removed' => ['Removed table', 'Reset creates this table again'],
    ];

    protected $description = 'Simpan / pulihkan baseline data dummy ddt* (dump .sql + file upload yang dirujuk)';

    public static function dir(): string
    {
        return storage_path('app/ddt-baseline');
    }

    public static function manifest(): ?array
    {
        $f = self::dir().'/manifest.json';

        return is_file($f) ? json_decode(file_get_contents($f), true) : null;
    }

    // Struktur tabel di dump mysqldump: [tabel => [kolom, ...]]. Hanya blok CREATE TABLE (view ditulis mysqldump di dalam
    // komentar /*!50001 CREATE VIEW */, jadi tidak ikut); baris kolom diawali backtick, baris KEY / CONSTRAINT tidak.
    /**
     * @param  iterable<string>  $lines
     * @return array<string, list<string>>
     */
    public static function dumpSchema(iterable $lines): array
    {
        $tables = [];
        $table = null;
        foreach ($lines as $line) {
            if ($table === null) {
                if (str_starts_with($line, 'CREATE TABLE `') && preg_match('/^CREATE TABLE `([^`]+)`/', $line, $m)) {
                    $table = $m[1];
                    $tables[$table] = [];
                }
            } elseif (str_starts_with($line, ')')) {
                $table = null;
            } elseif (preg_match('/^\s+`([^`]+)`/', $line, $m)) {
                $tables[$table][] = $m[1];
            }
        }

        return $tables;
    }

    // Beda struktur satu database: $dump = baseline, $live = sekarang (keduanya [tabel => [kolom]]).
    /** @return list<array{db: string, table: string, kind: string, columns: list<string>}> */
    public static function diffSchema(string $db, array $dump, array $live): array
    {
        $rows = [];
        foreach ($live as $t => $cols) {
            if (! isset($dump[$t])) {
                $rows[] = ['db' => $db, 'table' => $t, 'kind' => 'table_added', 'columns' => []];

                continue;
            }
            if ($added = array_values(array_diff($cols, $dump[$t]))) {
                $rows[] = ['db' => $db, 'table' => $t, 'kind' => 'column_added', 'columns' => $added];
            }
            if ($removed = array_values(array_diff($dump[$t], $cols))) {
                $rows[] = ['db' => $db, 'table' => $t, 'kind' => 'column_removed', 'columns' => $removed];
            }
        }
        foreach (array_keys(array_diff_key($dump, $live)) as $t) {
            $rows[] = ['db' => $db, 'table' => $t, 'kind' => 'table_removed', 'columns' => []];
        }

        return $rows;
    }

    // Drift struktur ddt* sejak baseline disimpan. Hanya membaca: file dump + information_schema.
    /** @return list<array{db: string, table: string, kind: string, columns: list<string>}> */
    public static function drift(?array $dbs = null): array
    {
        $rows = [];
        foreach ($dbs ?? self::manifest()['databases'] ?? [] as $d) {
            $sql = self::dir()."/$d.sql";
            if (! is_file($sql)) {
                continue;
            }
            $live = [];
            foreach (DB::connection('tm2mst')->select("SELECT c.TABLE_NAME t, c.COLUMN_NAME c FROM information_schema.COLUMNS c
                JOIN information_schema.TABLES t ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME AND t.TABLE_TYPE = 'BASE TABLE'
                WHERE c.TABLE_SCHEMA = ? ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION", [$d]) as $r) {
                $live[$r->t][] = $r->c;
            }
            array_push($rows, ...self::diffSchema($d, self::dumpSchema(new SplFileObject($sql)), $live));
        }

        return $rows;
    }

    public static function describeDrift(array $r): string
    {
        [$name, $effect] = self::DRIFT[$r['kind']];

        return "{$r['db']}.{$r['table']}: $name".($r['columns'] ? ' '.implode(', ', $r['columns']) : '')." -- $effect";
    }

    public function handle(): int
    {
        $dbs = $this->databases();
        $action = $this->argument('action');
        if (! in_array($action, ['save', 'restore', 'check'], true)) {
            $this->error('action = save | restore | check');

            return self::FAILURE;
        }
        if ($action === 'check') {
            return $this->check($dbs);
        }
        if (! $this->option('force') && ! $this->confirm(($action === 'save' ? 'Timpa baseline dengan isi ' : 'Kembalikan ').implode(', ', $dbs).'?')) {
            return self::FAILURE;
        }

        return $action === 'save' ? $this->save($dbs) : $this->restore($dbs);
    }

    // Pengaman: semua koneksi bisnis harus menunjuk database dummy ddt* (situs mockup).
    private function databases(): array
    {
        $dbs = array_map(fn ($s) => DB::connection("tm2$s")->getDatabaseName(), DdtCopy::SUFFIXES);
        foreach ($dbs as $d) {
            if (! str_starts_with($d, 'ddt')) {
                throw new RuntimeException("ddt:baseline hanya untuk database dummy ddt* (--env=mockup); dapat: $d");
            }
        }
        $exist = array_column(DB::connection('tm2mst')->select('SELECT SCHEMA_NAME s FROM information_schema.SCHEMATA'), 's');

        return array_values(array_intersect($dbs, $exist));
    }

    private function save(array $dbs): int
    {
        $tmp = self::dir().'.tmp';
        File::deleteDirectory($tmp);
        File::ensureDirectoryExists($tmp.'/files');

        foreach ($dbs as $d) {
            $this->exec([$this->bin('mysqldump'), ...$this->conn(), '--single-transaction', '--no-tablespaces', '--routines', '--set-gtid-purged=OFF',
                '--default-character-set=utf8mb4', '--result-file='.$tmp."/$d.sql", $d]);
            $this->line("  dump $d  ".round(filesize("$tmp/$d.sql") / 1048576, 1).' MB');
        }

        $files = $this->referencedFiles($dbs);
        foreach ($files as $path) {
            File::ensureDirectoryExists(dirname("$tmp/files/$path"));
            File::copy(Storage::disk('public')->path($path), "$tmp/files/$path");
        }

        file_put_contents("$tmp/manifest.json", json_encode(['created_at' => now()->toDateTimeString(), 'by' => auth()->user()?->login ?? get_current_user(),
            'databases' => $dbs, 'files' => count($files)], JSON_PRETTY_PRINT));

        // Tukar setelah semua berhasil: baseline lama utuh bila dump gagal di tengah jalan.
        File::deleteDirectory(self::dir());
        File::moveDirectory($tmp, self::dir());
        $this->info('Baseline tersimpan: '.count($dbs).' database, '.count($files).' file -> '.self::dir());

        return self::SUCCESS;
    }

    private function restore(array $dbs): int
    {
        $m = self::manifest();
        if (! $m) {
            $this->error('Belum ada baseline. Jalankan dulu: php artisan ddt:baseline save --env=mockup');

            return self::FAILURE;
        }

        if ($drift = self::drift($dbs)) {
            foreach ($drift as $r) {
                $this->line('  '.self::describeDrift($r));
            }
            if (! $this->option('allow-drift')) {
                $this->error('Reset refused, nothing was changed: the structure of '.count($drift).' table(s) changed after the baseline of '.$m['created_at'].'. Save a new baseline first (or force with --allow-drift).');

                return self::FAILURE;
            }
            $this->warn('  --allow-drift: tables go back to the baseline structure.');
        }

        foreach ($dbs as $d) {
            $sql = self::dir()."/$d.sql";
            if (! is_file($sql)) {
                $this->warn("  lewati $d (tidak ada di baseline)");

                continue;
            }
            $this->exec([$this->bin('mysql'), ...$this->conn(), '--default-character-set=utf8mb4', $d], fopen($sql, 'r'));
            $this->line("  pulih $d");
        }

        $n = 0;
        foreach (File::allFiles(self::dir().'/files') as $f) {
            Storage::disk('public')->put(str_replace('\\', '/', $f->getRelativePathname()), file_get_contents($f->getPathname()));
            $n++;
        }
        $this->info("Data dummy dikembalikan ke baseline {$m['created_at']} ($n file).");

        return self::SUCCESS;
    }

    private function check(array $dbs): int
    {
        $m = self::manifest();
        if (! $m) {
            $this->error('Belum ada baseline. Jalankan dulu: php artisan ddt:baseline save --env=mockup');

            return self::FAILURE;
        }
        $drift = self::drift($dbs);
        foreach ($drift as $r) {
            $this->line('  '.self::describeDrift($r));
        }
        $this->info($drift ? count($drift).' table(s) changed after the baseline of '.$m['created_at'].'.' : 'No structure change since the baseline of '.$m['created_at'].'.');

        return $drift ? self::FAILURE : self::SUCCESS;
    }

    // Nilai kolom bernama *path* di ddt* yang benar-benar ada di disk public (artwork, lampiran, foto).
    private function referencedFiles(array $dbs): array
    {
        $db = DB::connection('tm2mst');
        $in = implode(',', array_fill(0, count($dbs), '?'));
        $cols = $db->select("SELECT c.TABLE_SCHEMA s, c.TABLE_NAME t, c.COLUMN_NAME c FROM information_schema.COLUMNS c
            JOIN information_schema.TABLES t ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME AND t.TABLE_TYPE = 'BASE TABLE'
            WHERE c.TABLE_SCHEMA IN ($in) AND c.DATA_TYPE IN ('varchar','text') AND (c.COLUMN_NAME LIKE '%path%' OR c.COLUMN_NAME LIKE '%photo%')", $dbs);
        $disk = Storage::disk('public');

        return collect($cols)->flatMap(fn ($c) => $db->table("{$c->s}.{$c->t}")->whereNotNull($c->c)->where($c->c, '<>', '')->distinct()->pluck($c->c))
            ->map(fn ($p) => ltrim(preg_replace('#^/?storage/#', '', (string) $p), '/'))
            ->filter(fn ($p) => ! str_contains($p, '..') && $disk->exists($p))->unique()->values()->all();
    }

    private function conn(): array
    {
        $c = config('database.connections.tm2mst');

        return ['--host='.$c['host'], '--port='.$c['port'], '--user='.$c['username']];
    }

    private function bin(string $name): string
    {
        $dir = env('MYSQL_BIN_DIR', 'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin');
        $exe = rtrim($dir, '\\/').DIRECTORY_SEPARATOR.$name.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '');

        return is_file($exe) ? $exe : $name;
    }

    // Kata sandi lewat MYSQL_PWD (tidak tampil di daftar proses / argumen).
    private function exec(array $cmd, $input = null): void
    {
        $p = new Process($cmd, null, ['MYSQL_PWD' => config('database.connections.tm2mst.password')], $input, null);
        $p->run();
        if (! $p->isSuccessful()) {
            throw new RuntimeException(basename($cmd[0]).' gagal: '.trim($p->getErrorOutput() ?: $p->getOutput()));
        }
    }
}
