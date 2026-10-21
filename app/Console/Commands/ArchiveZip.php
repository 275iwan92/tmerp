<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use ZipArchive;

/*
 * REMARK
 * Tujuan       : Mengarsipkan berkas usang (v1 diganti v2, dst.) ke ZIP di tmerp_unused, lalu menghapus berkasnya dari pohon kode. Aturan baku D.26 (Jo 2026-10-21): "arsipkan" = masuk ZIP, bukan berkas lepas di situs.
 * Sumber hulu  : Daftar path (atau pola glob) berkas yang diarsipkan, nama batch.
 * Input        : php artisan archive:zip <batch> <path|glob>...   (path relatif ke root proyek; batch berakhiran _<yyyy-mm-dd>)
 *                Nama entri ZIP: resources/views/pages/<dir>/<nama>.blade.php -> <dir>_<nama>_v1_<tanggal>.blade.php; components/ -> component_...; tests/Feature/ -> test_...; selain itu nama berkas + _v1_<tanggal>.
 * Output       : tmerp_unused/<batch>.zip (ditambah bila sudah ada); berkas asli dihapus setelah isi ZIP diverifikasi. docs:check membaca kutipan `tmerp_unused/<nama>:baris` dari dalam ZIP.
 * Dipakai oleh : Agen, tiap kali memindahkan layar/komponen usang. Jangan lagi memakai git mv ke tmerp_unused.
 * Status       : Aktif (T10 tahap 5, 2026-10-21).
 */
class ArchiveZip extends Command
{
    protected $signature = 'archive:zip {batch : nama ZIP tanpa .zip, mis. arsip_v1_2026-10-21} {paths* : path atau glob berkas}';

    protected $description = 'Arsipkan berkas usang ke ZIP di tmerp_unused dan hapus berkas aslinya (aturan D.26)';

    public function handle(): int
    {
        $dir = base_path('tmerp_unused');
        $batch = basename((string) $this->argument('batch'), '.zip');
        $zipPath = $dir.'/'.$batch.'.zip';
        $files = [];
        foreach ((array) $this->argument('paths') as $p) {
            $found = glob(base_path($p)) ?: (is_file(base_path($p)) ? [base_path($p)] : []);
            $files = array_merge($files, array_filter($found, 'is_file'));
        }
        $files = array_values(array_unique($files));
        if (! $files) {
            $this->error('Tidak ada berkas yang cocok.');

            return self::FAILURE;
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new RuntimeException("Gagal membuka $zipPath");
        }
        foreach ($files as $f) {
            $entry = $this->entryName($f, $batch);
            if ($zip->locateName($entry) !== false) {
                $this->warn('Sudah ada di ZIP, dilewati: '.$entry);

                continue;
            }
            $zip->addFile($f, $entry);
        }
        $zip->close();

        // Verifikasi isi sebelum menghapus apa pun.
        $check = new ZipArchive;
        $check->open($zipPath);
        foreach ($files as $f) {
            if ($check->getFromName($this->entryName($f, $batch)) !== file_get_contents($f)) {
                $check->close();
                throw new RuntimeException('Isi ZIP tidak sama dengan berkas: '.$f);
            }
        }
        $n = $check->numFiles;
        $check->close();

        foreach ($files as $f) {
            unlink($f);
        }
        $this->info(count($files).' berkas masuk '.basename($zipPath)." (total $n entri), berkas asli dihapus.");

        return self::SUCCESS;
    }

    // Nama entri = nama yang dikutip kartu TG: tmerp_unused/<entri>:baris.
    private function entryName(string $file, string $batch): string
    {
        $root = str_replace('\\', '/', base_path());
        $rel = ltrim(str_replace([$root, '\\'], ['', '/'], $file), '/');
        $date = preg_match('/(\d{4}-\d{2}-\d{2})$/', $batch, $m) ? $m[1] : date('Y-m-d');
        $rel = match (true) {
            str_starts_with($rel, 'resources/views/pages/') => substr($rel, 22),
            str_starts_with($rel, 'resources/views/components/') => 'component_'.substr($rel, 27),
            str_starts_with($rel, 'resources/views/partials/') => 'partial_'.substr($rel, 25),
            str_starts_with($rel, 'tests/Feature/') => 'test_'.substr($rel, 14),
            default => basename($rel),
        };
        $base = str_replace(['/', '-'], '_', $rel);
        $dot = strpos($base, '.');

        return substr($base, 0, $dot).'_v1_'.$date.substr($base, $dot);
    }
}
