<?php

/*
 * REMARK
 * Tujuan       : Menguji objek DevLib x-dl::review-feed (kartu blog + tombol keputusan + saringan) dan MdDigest (ringkasan berkas .md).
 * Sumber hulu  : ../devlib/src/Components/ReviewFeed.php, app/Services/Sys/MdDigest.php.
 * Input        : Kartu contoh dalam memori dan satu berkas .md sementara di storage/framework/testing.
 * Output       : Lulus/gagal; berkas sementara dihapus di tearDown. Tidak menulis ke database.
 * Dipakai oleh : Penguji (Claude) setelah mengubah objek review atau layar MD Review / Old Stories Review.
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Services\Sys\MdDigest;
use Devlib\Components\ReviewFeed;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ReviewFeedTest extends TestCase
{
    private const TMP = 'storage/framework/testing/review_digest_test.md';

    protected function tearDown(): void
    {
        @unlink(base_path(self::TMP));
        parent::tearDown();
    }

    public function test_feed_renders_one_card_per_post_with_a_button_per_choice(): void
    {
        $feed = new ReviewFeed(
            posts: [
                ['id' => 1, 'title' => 'First', 'summary' => 'One. Two.', 'decision' => 'KEEP', 'data' => ['decision' => 'KEEP']],
                ['id' => 2, 'title' => 'Second', 'summary' => 'Three.', 'decision' => '', 'data' => ['decision' => '']],
            ],
            choices: ['KEEP' => 'Keep', 'DISCARD' => 'Discard'],
            filters: [['label' => 'All'], ['label' => 'Not decided', 'where' => ['decision' => ['']]]],
            saveUrl: '/save',
        );

        $this->assertSame(2, $feed->count(['label' => 'All']));
        $this->assertSame(1, $feed->count(['label' => 'Not decided', 'where' => ['decision' => ['']]]));

        $html = Blade::render('<x-dl::review-feed :posts="$p" :choices="$c" :filters="$f" save-url="/save" />', [
            'p' => $feed->posts, 'c' => $feed->choices, 'f' => $feed->filters]);
        $this->assertSame(2, substr_count($html, 'data-rf-post'));
        $this->assertSame(4, substr_count($html, 'data-rf-choice'));
        $this->assertStringContainsString('aria-pressed="true"', $html);
    }

    public function test_digest_takes_title_and_first_sentences_and_skips_tables_and_code(): void
    {
        file_put_contents(base_path(self::TMP), "# Judul Berkas\n\n| a | b |\n|---|---|\n\n> **Ringkasan** pertama. Kalimat kedua di sini! Ketiga?\n\n```\ncode jangan dibaca.\n```\n\nKalimat keempat tidak ikut.\n");
        $d = MdDigest::of(self::TMP);

        $this->assertSame('Judul Berkas', $d['title']);
        $this->assertSame('Ringkasan pertama. Kalimat kedua di sini! Ketiga?', $d['summary']);
    }

    public function test_digest_of_missing_file_is_empty(): void
    {
        $this->assertSame(['title' => null, 'summary' => ''], MdDigest::of('docs/does_not_exist_review_digest.md'));
    }
}
