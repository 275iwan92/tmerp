<?php

namespace Tests\Feature;

use Tests\TestCase;

/*
 * REMARK
 * Tujuan       : Menjaga concept:find menemukan konsep yang sudah ada (tipe produk -> prod_type.type_code) lewat sinonim Indonesia-Inggris.
 * Sumber hulu  : App\Console\Commands\ConceptFind, docs/tg/_synonyms.txt, information_schema.
 * Input        : Kata 'tipe produk'. Output: assert keluaran memuat tabel.kolom dan kartu TG.
 * Dipakai oleh : php artisan test --filter=ConceptFindTest.
 * Status       : BARU 2026-10-16.
 */
class ConceptFindTest extends TestCase
{
    public function test_indonesian_term_finds_the_product_type_column_through_synonyms(): void
    {
        $this->artisan('concept:find', ['term' => 'tipe produk'])
            ->expectsOutputToContain('prod_type.type_code')
            ->expectsOutputToContain('10_card_product-type.md')
            ->assertExitCode(0);
    }
}
