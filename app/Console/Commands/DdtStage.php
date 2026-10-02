<?php

namespace App\Console\Commands;

use App\Support\Dummy\StageBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use SplFileObject;

// Set data dummy per STAGE (user 2026-10-02): stage 1 = semua dokumen belum di-Confirm / belum diproses, stage 2 = sudah maju
// satu langkah dan menunggu langkah berikutnya, dst. Tiap set = dump .sql yang dipulihkan lewat tombol Restore di
// Developer > Data Dummy, supaya tiap langkah alur bisa diuji berulang dari titik awal yang pasti.
// Beda dari baseline (DdtBaseline): dump di sini HANYA DATA (tanpa CREATE TABLE). Restore = kosongkan tabel yang ada di set
// lalu isi lagi, jadi kolom yang ditambah migration setelah set disimpan tetap ada (terisi nilai bawaan) dan semua set
// tetap bisa dipakai. Yang membuat set tidak bisa dipulihkan hanya kolom / tabel yang DIHAPUS -> bangun ulang set itu.
// ddtsys (akun, menu, security) tidak ikut, sama seperti baseline.
//   php artisan ddt:stage list --env=mockup
//   php artisan ddt:stage save mixed --label="Mixed dummy" --env=mockup
//   php artisan ddt:stage restore stage-01 --env=mockup
//   php artisan ddt:stage build --env=mockup      (bangun ulang semua set stage lewat aturan aplikasi: App\Support\Dummy\StageBuilder)
class DdtStage extends DdtBaseline
{
    protected $signature = 'ddt:stage {action : list|save|restore|delete|build} {key? : nama set (huruf kecil, angka, tanda minus)}
        {--label= : judul set} {--note= : isi set, satu kalimat} {--dry-run : build tanpa menyimpan apa pun (semua dibatalkan)}
        {--force : tanpa konfirmasi}';

    protected $description = 'Set data dummy per stage: simpan / pulihkan / bangun ulang (dump .sql data saja)';

    // Drift yang membuat dump tidak bisa dimuat lagi (INSERT menyebut kolom / tabel yang sudah tidak ada).
    public const BLOCKING = ['column_removed', 'table_removed'];

    public static function root(): string
    {
        return storage_path('app/ddt-stages');
    }

    public static function path(string $key): string
    {
        return self::root().'/'.$key;
    }

    public static function validKey(string $key): bool
    {
        // Awalan "_" = set buatan sistem (mis. _before-build).
        return (bool) preg_match('/^_?[a-z0-9][a-z0-9-]{0,39}$/', $key);
    }

    public static function stage(string $key): ?array
    {
        $f = self::path($key).'/manifest.json';

        return self::validKey($key) && is_file($f) ? json_decode(file_get_contents($f), true) : null;
    }

    /** @return list<array> manifest semua set, urut nama */
    public static function sets(): array
    {
        $keys = is_dir(self::root()) ? array_map('basename', File::directories(self::root())) : [];
        sort($keys);

        return array_values(array_filter(array_map(fn ($k) => self::stage($k), $keys)));
    }

    // Struktur ddt* sekarang vs saat set disimpan. Hanya membaca.
    /** @return list<array{db: string, table: string, kind: string, columns: list<string>}> */
    public static function stageDrift(array $manifest): array
    {
        $rows = [];
        foreach ($manifest['schema'] as $db => $saved) {
            array_push($rows, ...self::diffSchema($db, $saved, self::liveSchema($db)));
        }

        return $rows;
    }

    /** @return list<array> drift yang menghalangi Restore */
    public static function blocking(array $manifest): array
    {
        return array_values(array_filter(self::stageDrift($manifest), fn ($r) => in_array($r['kind'], self::BLOCKING, true)));
    }

    /** @return array<string, list<string>> [tabel => [kolom]] BASE TABLE satu database */
    public static function liveSchema(string $db): array
    {
        $live = [];
        foreach (DB::connection('tm2mst')->select("SELECT c.TABLE_NAME t, c.COLUMN_NAME c FROM information_schema.COLUMNS c
            JOIN information_schema.TABLES t ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME AND t.TABLE_TYPE = 'BASE TABLE'
            WHERE c.TABLE_SCHEMA = ? ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION", [$db]) as $r) {
            $live[$r->t][] = $r->c;
        }

        return $live;
    }

    /** @return array<string, string> [tabel => "jumlah baris/CHECKSUM TABLE"] */
    public static function checksums(string $db, array $tables): array
    {
        $c = DB::connection('tm2mst');
        $out = [];
        foreach ($tables as $t) {
            $out[$t] = $c->table("$db.$t")->count().'/'.$c->selectOne("CHECKSUM TABLE `$db`.`$t`")->Checksum;
        }

        return $out;
    }

    // Bagian dump mysqldump (data saja) untuk tabel tertentu: kepala & ekor dump (SET ... sesi) selalu ikut, blok
    // "-- Dumping data for table `x`" hanya untuk tabel yang diminta.
    /**
     * @param  iterable<string>  $lines
     * @param  list<string>  $tables
     * @return \Generator<string>
     */
    public static function dumpOf(iterable $lines, array $tables): \Generator
    {
        $want = array_flip($tables);
        $pass = true;
        foreach ($lines as $line) {
            if (str_starts_with($line, '-- Dumping data for table `') && preg_match('/^-- Dumping data for table `([^`]+)`/', $line, $m)) {
                $pass = isset($want[$m[1]]);
            } elseif (str_starts_with($line, '/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE')) {
                $pass = true;
            }
            if ($pass) {
                yield $line;
            }
        }
    }

    public function handle(): int
    {
        $dbs = $this->databases();
        $action = $this->argument('action');
        $key = (string) $this->argument('key');

        if ($action === 'list') {
            $this->table(['Set', 'Label', 'Saved', 'By', 'Files'], array_map(fn ($m) => [$m['key'], $m['label'], $m['created_at'], $m['by'], $m['files']], self::sets()));

            return self::SUCCESS;
        }
        if ($action === 'build') {
            return $this->build();
        }
        if (! in_array($action, ['save', 'restore', 'delete'], true)) {
            $this->error('action = list | save | restore | delete | build');

            return self::FAILURE;
        }
        if (! self::validKey($key)) {
            $this->error('Set name: lowercase letters, digits and minus only (max 40), e.g. stage-01.');

            return self::FAILURE;
        }
        $ask = ['save' => "Simpan isi data dummy sekarang sebagai set '$key'?", 'restore' => "Ganti isi data dummy dengan set '$key'?", 'delete' => "Hapus set '$key'?"][$action];
        if (! $this->option('force') && ! $this->confirm($ask)) {
            return self::FAILURE;
        }

        return match ($action) {
            'save' => $this->saveStage($dbs, $key, (string) ($this->option('label') ?: (self::stage($key)['label'] ?? $key)), (string) ($this->option('note') ?: (self::stage($key)['note'] ?? ''))),
            'restore' => $this->restoreStage($dbs, $key),
            'delete' => $this->deleteStage($key),
        };
    }

    public function saveStage(array $dbs, string $key, string $label, string $note, bool $built = false): int
    {
        $tmp = self::path($key).'.tmp';
        File::deleteDirectory($tmp);
        File::ensureDirectoryExists($tmp.'/files');

        $schema = $checksums = [];
        foreach ($dbs as $d) {
            // Data saja + nama kolom di tiap INSERT: bisa dimuat ke tabel yang kolomnya sudah bertambah.
            $this->exec([$this->bin('mysqldump'), ...$this->conn(), '--single-transaction', '--no-tablespaces', '--set-gtid-purged=OFF', '--no-create-info',
                '--complete-insert', '--skip-triggers', '--hex-blob', '--default-character-set=utf8mb4', '--result-file='.$tmp."/$d.sql", $d]);
            $schema[$d] = self::liveSchema($d);
            $checksums[$d] = self::checksums($d, array_keys($schema[$d]));
        }
        $files = $this->referencedFiles($dbs);
        foreach ($files as $path) {
            File::ensureDirectoryExists(dirname("$tmp/files/$path"));
            File::copy(Storage::disk('public')->path($path), "$tmp/files/$path");
        }
        file_put_contents("$tmp/manifest.json", json_encode(['key' => $key, 'label' => $label, 'note' => $note, 'built' => $built,
            'created_at' => now()->toDateTimeString(), 'by' => auth()->user()?->login ?? get_current_user(), 'databases' => $dbs,
            'files' => count($files), 'schema' => $schema, 'checksums' => $checksums]));

        File::deleteDirectory(self::path($key));
        File::moveDirectory($tmp, self::path($key));
        $this->info("Set '$key' saved: ".count($dbs).' database, '.count($files).' file.');

        return self::SUCCESS;
    }

    public function restoreStage(array $dbs, string $key): int
    {
        $m = self::stage($key);
        if (! $m) {
            $this->error("Set '$key' does not exist.");

            return self::FAILURE;
        }
        if ($blocking = self::blocking($m)) {
            foreach ($blocking as $r) {
                $this->line('  '.self::describeDrift($r));
            }
            $this->error("Restore refused, nothing was changed: set '$key' holds columns or tables that no longer exist. Build or save this set again.");

            return self::FAILURE;
        }

        foreach ($dbs as $d) {
            $sql = self::path($key)."/$d.sql";
            if (! is_file($sql) || ! isset($m['schema'][$d])) {
                $this->warn("  skip $d (not in this set)");

                continue;
            }
            // Hanya tabel yang ada di set (tabel yang dibuat setelah set disimpan tidak disentuh) DAN isinya berbeda dari set:
            // master & data lama yang besar (wilayah, legacy) hampir selalu sama, jadi restore antar stage hanya memuat
            // tabel transaksi -> detik, bukan menit.
            $tables = array_values(array_intersect(array_keys($m['schema'][$d]), array_keys(self::liveSchema($d))));
            $live = self::checksums($d, $tables);
            $changed = array_values(array_filter($tables, fn ($t) => ($m['checksums'][$d][$t] ?? null) !== $live[$t]));
            if (! $changed) {
                continue;
            }
            $this->exec([$this->bin('mysql'), ...$this->conn(), '--default-character-set=utf8mb4', $d],
                "SET FOREIGN_KEY_CHECKS=0;\n".implode('', array_map(fn ($t) => "TRUNCATE TABLE `$t`;\n", $changed)));
            $this->exec([$this->bin('mysql'), ...$this->conn(), '--default-character-set=utf8mb4', $d], self::dumpOf(new SplFileObject($sql), $changed));
            $this->line("  $d: ".count($changed).' table(s) restored');
        }

        $n = 0;
        $disk = Storage::disk('public');
        foreach (File::allFiles(self::path($key).'/files') as $f) {
            $path = str_replace('\\', '/', $f->getRelativePathname());
            if (! $disk->exists($path) || $disk->size($path) !== $f->getSize()) {
                $disk->put($path, file_get_contents($f->getPathname()));
                $n++;
            }
        }
        $this->info("Dummy data is now set '$key' ({$m['label']}, saved {$m['created_at']}, $n file put back).");

        return self::SUCCESS;
    }

    private function deleteStage(string $key): int
    {
        if (! self::stage($key)) {
            $this->error("Set '$key' does not exist.");

            return self::FAILURE;
        }
        File::deleteDirectory(self::path($key));
        $this->info("Set '$key' deleted.");

        return self::SUCCESS;
    }

    // Bangun ulang semua set stage. Isi data dummy sekarang disimpan dulu (set _before-build) dan dikembalikan sesudahnya,
    // jadi yang berubah hanya file set stage -- kecuali --dry-run, yang membatalkan semuanya lewat transaksi.
    private function build(): int
    {
        $dbs = $this->databases();
        $builder = new StageBuilder($this);
        if ($this->option('dry-run')) {
            $builder->run(null);
            $this->info('Dry run finished: every step ran, nothing was saved.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm('Bangun ulang semua set stage? Isi data dummy sekarang disimpan dan dikembalikan sesudahnya.')) {
            return self::FAILURE;
        }
        $this->saveStage($dbs, '_before-build', 'State before the last stage build', 'Saved automatically by ddt:stage build.');
        try {
            $builder->run(fn (string $key, string $label, string $note) => $this->saveStage($dbs, $key, $label, $note, true));
        } finally {
            $this->restoreStage($dbs, '_before-build');
        }

        return self::SUCCESS;
    }
}
