<?php

namespace Tests\Feature;

use Tests\TestCase;

/*
 * REMARK
 * Tujuan       : Menjaga kartu Technical Guide (docs/tg) tetap cocok dengan DB dan kode: php artisan docs:check tidak boleh ada ERROR.
 * Sumber hulu  : App\Console\Commands\DocsCheck, docs/tg, docs/tg/_allow.txt.
 * Input        : Tidak ada (membaca repo dan information_schema). Output: assert kode keluar 0.
 * Dipakai oleh : php artisan test --filter=DocsCheckTest.
 * Status       : BARU 2026-10-16.
 */
class DocsCheckTest extends TestCase
{
    public function test_technical_guide_cards_have_no_broken_references(): void
    {
        $this->artisan('docs:check')->assertExitCode(0);
    }
}
