<?php

/*
 * REMARK
 * Tujuan       : Menjaga level draft dan relevel di MasterCardDummyEngine: produk tak dikenal / level salah tidak menghapus apa pun.
 * Sumber hulu  : MasterCardDummyEngine::counts, ::relevel; folder docs/attachments/mastercard.
 * Input        : Baca saja (database aktif); relevel hanya dipanggil dengan istilah yang tidak ada, jadi tidak menulis.
 * Output       : Lulus/gagal.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif; skip bila bukan database dummy ddt*.
 */

namespace Tests\Feature;

use App\Services\MasterCard\MasterCardDummyEngine;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MasterCardDummyEngineLevelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! str_starts_with(DB::connection('tm2mst')->getDatabaseName(), 'ddt')) {
            $this->markTestSkipped('Dummy engine only runs on ddt* databases.');
        }
    }

    public function test_draft_is_the_first_level_and_counted(): void
    {
        $this->assertSame('draft', MasterCardDummyEngine::LEVELS[0]);
        $this->assertArrayHasKey('draft', app(MasterCardDummyEngine::class)->counts());
    }

    public function test_relevel_with_unknown_product_or_level_changes_nothing(): void
    {
        $engine = app(MasterCardDummyEngine::class);
        $before = $engine->counts();
        $card = DB::connection('tm2mst')->table('prod_master_card')->count();

        $report = $engine->relevel(base_path('docs/attachments/mastercard'), ['ZZ-NO-SUCH-PRODUCT' => 'draft', 'DS002908' => 'nowhere'], 5000, 21);

        $this->assertSame('not found in the PDF folder', $report[0]['note']);
        $this->assertSame('unknown level nowhere', $report[1]['note']);
        $this->assertSame($before, $engine->counts());
        $this->assertSame($card, DB::connection('tm2mst')->table('prod_master_card')->count());
    }
}
