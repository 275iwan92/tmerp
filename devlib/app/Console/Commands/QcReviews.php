<?php

namespace App\Console\Commands;

use App\Support\Qc;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Untuk AGEN di awal sesi (user 2026-10-07): keputusan QC cepat dari devlib.test. Tanpa opsi = daftar Need improvement yang belum
// dikerjakan (beserta catatan) dan yang baru Approved; --resolve=<id> menandai satu catatan sudah dikerjakan.
// Jalankan dari folder devlib:  php artisan qc:reviews   |   php artisan qc:reviews --resolve=12 --by=iwan
class QcReviews extends Command
{
    protected $signature = 'qc:reviews {--resolve= : id_review catatan yang sudah dikerjakan} {--by=agent : nama agen penutup}';

    protected $description = 'Daftar keputusan QC (Need improvement terbuka + Approved terbaru) untuk agen';

    public function handle(): int
    {
        if (! Qc::ready()) {
            $this->warn('Tabel qc_review belum ada: php artisan migrate --path=database/migrations/2026_10_07_000001_create_qc_review_table.php');

            return self::SUCCESS;
        }
        if ($id = $this->option('resolve')) {
            $n = DB::table('qc_review')->where('id_review', (int) $id)->where('status', 'NEEDS_IMPROVEMENT')->whereNull('resolved_at')
                ->update(['resolved_by' => $this->option('by'), 'resolved_at' => now()]);
            $this->info($n ? "Catatan #{$id} ditandai selesai." : "Catatan #{$id} tidak ditemukan / sudah selesai.");

            return self::SUCCESS;
        }
        $open = Qc::open();
        $this->line('NEED IMPROVEMENT (belum dikerjakan): '.count($open));
        foreach ($open as $r) {
            $this->line("  #{$r['id_review']} [{$r['stage']}] {$r['item_key']} ({$r['item_name']}) {$r['reviewed_by']} {$r['reviewed_at']}");
            $this->line('     '.$r['note']);
        }
        $rel = Qc::releasable();
        $this->line('READY TO RELEASE (QC + UAT officer + UAT supervisor Approved): '.count($rel));
        foreach ($rel as $k => $n) {
            $this->line("  {$k} ({$n})");
        }
        $ok = DB::table('qc_review as r')->whereRaw('r.id_review = (select max(x.id_review) from qc_review x where x.item_key = r.item_key and x.stage = r.stage)')
            ->where('r.status', 'APPROVED')->orderByDesc('r.reviewed_at')->limit(30)->get();
        $this->line('APPROVED terbaru: '.$ok->count());
        foreach ($ok as $r) {
            $this->line("  [{$r->stage}] {$r->item_key} ({$r->item_name}) {$r->reviewed_by} {$r->reviewed_at}");
        }

        return self::SUCCESS;
    }
}
