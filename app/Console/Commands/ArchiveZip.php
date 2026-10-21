<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use ZipArchive;

/*
 * REMARK
 * Tujuan       : Mengarsipkan berkas usang (v1 diganti v2, dst.) ke ZIP di tmerp_unused, lalu menghapus berkasnya dari pohon kode. Aturan baku D.26 (Jo 2026-10-21): "arsipkan" = masuk ZIP, bukan berkas lepas di situs.
 * Sumber hulu  : Daftar path (atau pola glob) berkas yang diarsipkan, nama batch.
 * Input        : php artisan archive:zip <batch> <path|glob>...   (path relatif ke root proyek; entri ZIP = nama berkas saja)
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
        $zipPath = $dir.'/'.basename((string) $this->argument('batch'), '.zip').'.zip';
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
            if ($zip->locateName(basename($f)) !== false) {
                $this->warn('Sudah ada di ZIP, dilewati: '.basename($f));

                continue;
            }
            $zip->addFile($f, basename($f));
        }
        $zip->close();

        // Verifikasi isi sebelum menghapus apa pun.
        $check = new ZipArchive;
        $check->open($zipPath);
        foreach ($files as $f) {
            if ($check->getFromName(basename($f)) !== file_get_contents($f)) {
                $check->close();
                throw new RuntimeException('Isi ZIP tidak sama dengan berkas: '.basename($f));
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
}
