<?php

/*
 * REMARK
 * Tujuan       : Mengisi (atau menyegarkan) data layar MD Review dan Old Stories Review dari dua berkas hasil kerja Claude, tanpa menghapus keputusan Jo.
 * Sumber hulu  : docs/ringkas/md_review_tabel.md (tabel berkas + usulan) dan docs/ringkas/cerita_lama_penuh.md (kutipan pesan Jo dari riwayat sesi).
 * Input        : --md=<path> --stories=<path> (bawaan: berkas di docs/ringkas); --env=mockup untuk database ddt*.
 * Output       : Baris sys_md_review (kunci file_path; title + summary dari MdDigest, 2026-10-14) dan sys_story_review (kunci tanggal + sesi + awal pesan); kolom decision tidak disentuh.
 * Dipakai oleh : Dijalankan Claude setelah migrasi 2026_10_13_000600; ulangi bila berkas sumber diperbarui.
 * Status       : Aktif (ddt*).
 */

namespace App\Console\Commands;

use App\Models\Sys\SysMdReview;
use App\Models\Sys\SysStoryReview;
use App\Services\Sys\MdDigest;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ReviewImport extends Command
{
    protected $signature = 'review:import {--md= : path to md_review_tabel.md} {--stories= : path to cerita_lama_penuh.md}';

    protected $description = 'Load the MD Review and Old Stories Review screens from the review files (keeps Jo\'s decisions)';

    // Usulan dalam berkas (Indonesia) -> kode di database (English).
    private const PROPOSAL = ['PERTAHANKAN' => 'KEEP', 'RINGKAS' => 'CONDENSE', 'RAGU' => 'UNSURE', 'BUANG' => 'DISCARD'];

    public function handle(): int
    {
        $md = $this->option('md') ?: base_path('docs/ringkas/md_review_tabel.md');
        $stories = $this->option('stories') ?: base_path('docs/ringkas/cerita_lama_penuh.md');
        if (! is_file($md) || ! is_file($stories)) {
            $this->error('Source file not found: '.(is_file($md) ? $stories : $md));

            return self::FAILURE;
        }
        $this->info('MD files: '.$this->importMd((string) file_get_contents($md)));
        $this->info('Stories: '.$this->importStories((string) file_get_contents($stories)));

        return self::SUCCESS;
    }

    private function importMd(string $text): int
    {
        $n = 0;
        foreach (preg_split('/\R/', $text) as $line) {
            // | # | `path` | baris | tanggal | rujukan | **USULAN** | alasan | keputusan |
            if (! preg_match('/^\| \d+ \| `([^`]+)` \| (\d+) \| ([^|]*) \| ([^|]*) \| \*\*(\w+)\*\* \| (.*?) \|\s*\|$/u', trim($line), $m)) {
                continue;
            }
            $date = trim($m[3]);
            $refs = trim($m[4]);
            // Berkas yang sudah diarsipkan tidak punya prosa: ringkasan lama di database dipertahankan.
            $digest = MdDigest::of($m[1]);
            // Jumlah baris dibaca dari berkasnya (tabel sumber bisa basi setelah diringkas); berkas hilang = angka tabel.
            $lines = is_file(base_path($m[1])) ? substr_count((string) file_get_contents(base_path($m[1])), "\n") + 1 : (int) $m[2];
            $summary = $digest['summary'] !== '' ? ['title' => Str::limit((string) $digest['title'], 200, ''), 'summary' => Str::limit($digest['summary'], 600, '')] : [];
            SysMdReview::query()->updateOrCreate(['file_path' => $m[1]], $summary + [
                'line_count' => $lines,
                'last_changed' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null,
                'ref_count' => ctype_digit($refs) ? (int) $refs : null,
                'proposal' => self::PROPOSAL[$m[5]] ?? 'UNSURE',
                'reason' => Str::limit(trim($m[6]), 500, ''),
            ]);
            $n++;
        }

        return $n;
    }

    private function importStories(string $text): int
    {
        $n = 0;
        // ## #N 2026-08-15 sesi a01e3776 (1857 karakter) lalu baris kosong lalu teks
        preg_match_all('/^## #\d+ (\d{4}-\d{2}-\d{2}) sesi (\S+) \((\d+) karakter\)\R\R(.*?)(?=\R\R## #\d+ |\z)/ms', $text, $all, PREG_SET_ORDER);
        foreach ($all as $m) {
            $body = trim($m[4]);
            $existing = SysStoryReview::query()->where('msg_date', $m[1])->where('session_ref', $m[2])->get()
                ->first(fn ($r) => Str::startsWith($r->full_text, Str::limit($body, 80, '')));
            $data = ['msg_date' => $m[1], 'session_ref' => $m[2], 'char_count' => mb_strlen($body), 'full_text' => $body];
            $existing ? $existing->update($data) : SysStoryReview::query()->create($data);
            $n++;
        }

        return $n;
    }
}
