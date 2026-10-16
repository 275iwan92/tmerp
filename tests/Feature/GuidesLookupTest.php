<?php

/*
 * REMARK
 * Tujuan       : Menguji Guides: impor memisahkan bagian umum dan bagian rumus, pencarian tidak menyentuh rumus, dan rumus hanya tampil untuk yang berhak.
 * Sumber hulu  : App\Console\Commands\GuidesImport, App\Support\V2\Grid\Setup\GuidesLookup, tabel tm2sys.sys_guide (migration 2026_10_16_000100).
 * Input        : Satu folder panduan sementara di storage/app; baris uji dihapus sendiri; dilewati bila tabel belum ada.
 * Output       : Lulus / dilewati.
 * Dipakai oleh : php artisan test --filter=GuidesLookupTest
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use App\Models\Sys\SysGuide;
use App\Support\V2\Grid\Setup\GuidesLookup;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GuidesLookupTest extends TestCase
{
    private const DIR = 'storage/app/zz_guides_test';

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::connection('tm2sys')->hasTable('sys_guide')) {
            $this->markTestSkipped('sys_guide belum ada di database ini.');
        }
        File::ensureDirectoryExists(base_path(self::DIR.'/zztest'));
        File::put(base_path(self::DIR.'/zztest/01_demo.md'), "# Panduan Uji\n\nNarasi umum tentang jerapah biru.\n\n<!-- johiwan-only -->\n\n## Rumus\n\nHPP = rahasia kuda nil.\n");
        Artisan::call('guides:import', ['--path' => self::DIR]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path(self::DIR));
        if (Schema::connection('tm2sys')->hasTable('sys_guide')) {
            SysGuide::query()->where('slug', 'zztest-demo')->delete();
        }
        parent::tearDown();
    }

    public function test_import_splits_public_and_secret_parts(): void
    {
        $g = SysGuide::query()->where('slug', 'zztest-demo')->first();

        $this->assertSame('Panduan Uji', $g->title);
        $this->assertStringContainsString('jerapah biru', $g->body);
        $this->assertStringNotContainsString('kuda nil', $g->body);
        $this->assertStringContainsString('kuda nil', (string) $g->body_secret);
    }

    public function test_search_never_reads_the_secret_part(): void
    {
        $lookup = new GuidesLookup;

        $this->assertCount(1, array_filter($lookup->feed('jerapah')['posts'], fn ($p) => $p['title'] === 'Panduan Uji'));
        $this->assertCount(0, array_filter($lookup->feed('kuda nil')['posts'], fn ($p) => $p['title'] === 'Panduan Uji'));
    }

    public function test_formulas_show_only_to_allowed_logins(): void
    {
        $id = (int) SysGuide::query()->where('slug', 'zztest-demo')->value('id_guide');
        $lookup = new GuidesLookup;

        Auth::login(SecUser::query()->where('login', '<>', 'johiwan')->where('activate', 1)->firstOrFail());
        $this->assertStringNotContainsString('kuda nil', $lookup->article($id)['body']);

        Auth::login(SecUser::query()->where('login', 'johiwan')->firstOrFail());
        $this->assertStringContainsString('kuda nil', $lookup->article($id)['body']);
    }
}
