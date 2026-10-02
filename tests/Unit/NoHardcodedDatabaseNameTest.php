<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

// Situs dummy (mockup.test) memakai kode yang sama dengan nama database ddt* dari .env. SQL yang menulis
// `tm2mst.tabel` langsung akan membaca/MENULIS data real -> wajib lewat App\Support\DbName::table().
class NoHardcodedDatabaseNameTest extends TestCase
{
    // Berkas yang sengaja menyebut tm2* sebagai DATA/label, bukan SQL (alasan ditulis).
    private const ALLOWED = [
        'app/Services/Inventory/StockPostingService.php' => 'reference_name = label tersimpan di data, bukan nama database',
        'app/Services/Hrm/EmployeeMergeService.php' => 'REFERENCES diterjemahkan lewat DbName::table()',
        'app/Console/Commands/DdtCopy.php' => 'alat salin tm2* -> ddt*',
        'app/Console/Commands/DdtCopySys.php' => 'alat salin tm2sys -> ddtsys',
    ];

    // Berkas yang hanya BARIS tertentunya boleh menyebut tm2* (pola baris); baris lain di berkas itu tetap diperiksa.
    private const ALLOWED_LINES = [
        // Daftar engine: 'reads' / 'writes' = teks dokumentasi tabel yang dibaca / ditulis engine (tampil di Engines dan
        // engines.json), tidak pernah dijalankan sebagai SQL. Nama tm2* = nama rancangan, sama di mockup.test.
        'app/Support/Engines/EngineRegistry.php' => '/^\s*[\'"](reads|writes)[\'"]\s*=>\s*\[/',
    ];

    public function test_no_sql_names_a_tm2_database_directly(): void
    {
        $root = dirname(__DIR__, 2);
        $hits = [];
        foreach (['app', 'resources/views', 'routes'] as $dir) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir")) as $file) {
                if (! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }
                $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
                if (isset(self::ALLOWED[$rel])) {
                    continue;
                }
                foreach (file($file->getPathname()) as $n => $line) {
                    // Lewati baris komentar (PHP & Blade).
                    if (preg_match('#^\s*(//|\*|/\*|\{\{--)#', $line)) {
                        continue;
                    }
                    if (isset(self::ALLOWED_LINES[$rel]) && preg_match(self::ALLOWED_LINES[$rel], $line)) {
                        continue;
                    }
                    // DbName::table() = sudah aman; Src::of()/'source' => = label tooltip sumber data, bukan SQL.
                    if (preg_match('/DbName::table\(|Src::(of|calc)\(|[\'"]source[\'"]\s*=>/', $line)) {
                        continue;
                    }
                    if (preg_match('/[\'"`]tm2(mst|sdi|pur|inv|prd|acc|hrm|hrr|pyr|sys)`?\.`?[a-z_]+/', $line)) {
                        $hits[] = "$rel:".($n + 1).'  '.trim($line);
                    }
                }
            }
        }

        $this->assertSame([], $hits, "Nama database tm2* ditulis langsung (pakai App\Support\DbName::table()):\n".implode("\n", $hits));
    }
}
