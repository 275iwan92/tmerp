<?php

namespace App\Support\Dummy;

use Illuminate\Console\Command;
use RuntimeException;

// Membangun set data dummy per stage lewat ATURAN APLIKASI (bukan mengetik baris ke tabel): stage N = stage N-1 yang
// dimajukan satu langkah oleh Confirm / Approve / Post yang sama dengan tombol di layar. Aturan yang gagal saat
// memajukan stage = bug alur yang ketemu di sini.
final class StageBuilder
{
    public function __construct(private Command $out) {}

    // $save(key, label, note) menyimpan isi ddt* sekarang sebagai set; null = dry run (tidak ada yang disimpan).
    public function run(?callable $save): void
    {
        throw new RuntimeException('Stage builder is not built yet.');
    }
}
