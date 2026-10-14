<?php

/*
 * REMARK
 * Tujuan       : Menguji layar Old Docs Library: indeks (jenis + cuplikan pencarian) dan satu artikel penuh tanpa front matter / judul ganda, hanya baca.
 * Sumber hulu  : App\Support\V2\Grid\Setup\DocsLibraryLookup, tabel tm2sys.sys_md_library (migration 2026_10_14_000400).
 * Input        : Dua baris uji disisipkan lalu dihapus sendiri; dilewati bila tabel belum ada (belum dirilis ke database ini).
 * Output       : Lulus / dilewati.
 * Dipakai oleh : php artisan test --filter=DocsLibraryLookupTest
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Sys\SysMdLibrary;
use App\Support\V2\Grid\Setup\DocsLibraryLookup;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DocsLibraryLookupTest extends TestCase
{
    private const PATH = 'zz_test_docs_library/flow_demo.md';

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::connection('tm2sys')->hasTable('sys_md_library')) {
            $this->markTestSkipped('sys_md_library belum ada di database ini.');
        }
        SysMdLibrary::query()->where('original_path', self::PATH)->delete();
        SysMdLibrary::query()->create(['file_name' => 'flow_demo.md', 'original_path' => self::PATH, 'title' => 'Alur Demo Uji', 'summary' => 'Ringkasan uji.',
            'line_count' => 5, 'reason' => 'uji', 'archived_at' => '2026-10-14',
            'body' => "---\nname: flow_demo\n---\n# Alur Demo Uji\n\nKalimat pembuka tentang zebra kuning.\n\n| a | b |\n|---|---|\n| 1 | 2 |\n"]);
    }

    protected function tearDown(): void
    {
        if (Schema::connection('tm2sys')->hasTable('sys_md_library')) {
            SysMdLibrary::query()->where('original_path', self::PATH)->delete();
        }
        parent::tearDown();
    }

    public function test_index_searches_inside_the_text_and_returns_a_snippet(): void
    {
        $feed = (new DocsLibraryLookup)->feed('zebra');
        $post = collect($feed['posts'])->firstWhere('title', 'Alur Demo Uji');

        $this->assertNotNull($post);
        $this->assertStringContainsString('zebra', $post['summary']);
        $this->assertStringNotContainsString('name: flow_demo', $post['summary']);
        $this->assertStringContainsString('?doc='.$post['id'], $post['url']);
        $this->assertSame('Other', $post['data']['kind']);
    }

    public function test_article_drops_front_matter_and_duplicate_title(): void
    {
        $id = SysMdLibrary::query()->where('original_path', self::PATH)->value('id_md_library');
        $article = (new DocsLibraryLookup)->article((int) $id);

        $this->assertSame('Alur Demo Uji', $article['title']);
        $this->assertStringNotContainsString('name: flow_demo', $article['body']);
        $this->assertStringStartsNotWith('# Alur Demo Uji', ltrim($article['body']));
        $this->assertStringContainsString('zebra kuning', $article['body']);
        $this->assertNull((new DocsLibraryLookup)->article(0));
    }

    public function test_library_is_read_only(): void
    {
        $this->expectException(ValidationException::class);
        (new DocsLibraryLookup)->save([['id' => 1, 'original_path' => 'x']]);
    }
}
