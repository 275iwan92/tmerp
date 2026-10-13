<?php

/*
 * REMARK
 * Tujuan       : Membaca antrean "Process with Claude" dari layar MD Review / Old Stories Review dan menutupnya setelah diproses.
 * Sumber hulu  : sys_review_request (REQUESTED) + keputusan di sys_md_review / sys_story_review.
 * Input        : Tanpa opsi = daftar permintaan terbuka dan isi keputusannya; --done=<id> [--note=...] = tandai selesai.
 * Output       : Teks di konsol (berkas per keputusan, atau pesan yang di-Adopt); baris permintaan menjadi DONE.
 * Dipakai oleh : Claude di awal sesi (pola sama dengan gen:requests dan qc:reviews); jalankan dengan --env=mockup untuk ddt*.
 * Status       : Aktif (ddt*).
 */

namespace App\Console\Commands;

use App\Models\Sys\SysMdReview;
use App\Models\Sys\SysReviewRequest;
use App\Models\Sys\SysStoryReview;
use App\Services\Sys\ReviewRequestService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ReviewRequests extends Command
{
    protected $signature = 'review:requests {--done= : id of the request to close} {--note= : note saved with --done}';

    protected $description = 'List the open Process with Claude requests (MD Review, Old Stories Review) or close one';

    public function handle(ReviewRequestService $service): int
    {
        if ($id = $this->option('done')) {
            $req = $service->done((int) $id, 'claude', $this->option('note'));
            $this->info("Request {$req->id_review_request} ({$req->scope}) marked DONE.");

            return self::SUCCESS;
        }

        $open = SysReviewRequest::query()->where('status', SysReviewRequest::REQUESTED)->orderBy('id_review_request')->get();
        if ($open->isEmpty()) {
            $this->info('No open requests.');

            return self::SUCCESS;
        }
        foreach ($open as $req) {
            $this->line("== Request {$req->id_review_request} · {$req->scope} · {$req->requested_at} by {$req->requested_by} · {$req->n_decided}/{$req->n_total} decided ({$req->summary})");
            $req->scope === 'MD' ? $this->listMd() : $this->listStories();
        }

        return self::SUCCESS;
    }

    private function listMd(): void
    {
        foreach (['DISCARD', 'CONDENSE', 'KEEP'] as $decision) {
            $rows = SysMdReview::query()->where('decision', $decision)->orderBy('file_path')->get();
            $this->line("  {$decision} ({$rows->count()})");
            if ($decision !== 'KEEP') {
                $rows->each(fn ($r) => $this->line("    - {$r->file_path} [{$r->line_count} lines; proposed {$r->proposal}]"));
            }
        }
    }

    private function listStories(): void
    {
        foreach (['ADOPT', 'LATER', 'IGNORE'] as $decision) {
            $rows = SysStoryReview::query()->where('decision', $decision)->orderBy('msg_date')->get();
            $this->line("  {$decision} ({$rows->count()})");
            if ($decision === 'ADOPT') {
                $rows->each(fn ($r) => $this->line("    - #{$r->id_story_review} {$r->msg_date} sesi {$r->session_ref}: ".Str::limit(Str::squish($r->full_text), 140)));
            }
        }
    }
}
