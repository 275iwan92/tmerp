<?php

namespace App\Console\Commands;

use App\Support\Qc;
use Illuminate\Console\Command;

// Untuk AGEN di awal sesi (user 2026-10-07): keputusan QC cepat dari devlib.test. Tanpa opsi = daftar Need improvement yang belum
// dikerjakan (beserta catatan) dan yang baru Approved; --resolve=<id> menandai satu catatan sudah dikerjakan.
// Jalankan dari folder devlib:  php artisan qc:reviews   |   php artisan qc:reviews --resolve=12 --by=iwan
class QcReviews extends Command
{
    protected $signature = 'qc:reviews {--resolve= : id_review catatan yang sudah dikerjakan} {--by=agent : nama agen penutup}';

    protected $description = 'Daftar keputusan QC (Need improvement terbuka + Approved terbaru) untuk agen';

    public function handle(): int
    {
        $qc = Qc::store();
        if (! $qc->ready()) {
            $this->warn('Tabel qc_review belum ada: php artisan migrate --path=database/migrations/2026_10_07_000001_create_qc_review_table.php');

            return self::SUCCESS;
        }
        if ($id = $this->option('resolve')) {
            $n = $qc->resolve((int) $id, (string) $this->option('by'));
            $this->info($n ? "Catatan #{$id} ditandai selesai." : "Catatan #{$id} tidak ditemukan / sudah selesai.");

            return self::SUCCESS;
        }
        $open = $qc->open();
        $this->line('NEED IMPROVEMENT (belum dikerjakan): '.count($open));
        foreach ($open as $r) {
            $this->line("  #{$r['id_review']} [{$r['stage']}] {$r['item_key']} ({$r['item_name']}) {$r['reviewed_by']} {$r['reviewed_at']}");
            $this->line('     '.$r['note']);
        }
        $rel = $qc->releasable();
        $this->line('READY TO RELEASE (QC + UAT officer + UAT supervisor Approved): '.count($rel));
        foreach ($rel as $k => $n) {
            $this->line("  {$k} ({$n})");
        }
        $ok = $qc->approved();
        $this->line('APPROVED terbaru: '.$ok->count());
        foreach ($ok as $r) {
            $this->line("  [{$r->stage}] {$r->item_key} ({$r->item_name}) {$r->reviewed_by} {$r->reviewed_at}");
        }

        return self::SUCCESS;
    }
}
