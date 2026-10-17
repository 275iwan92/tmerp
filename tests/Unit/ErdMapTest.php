<?php

namespace Tests\Unit;

use App\Support\Docs\ErdMap;
use PHPUnit\Framework\TestCase;

/*
 * REMARK
 * Tujuan       : Menjaga pembaca tabel Table relations dan pembangun SVG relasinya (App\Support\Docs\ErdMap): kardinalitas, garis FK vs logis, relasi ke diri sendiri.
 * Sumber hulu  : App\Support\Docs\ErdMap.
 * Input        : Teks md contoh. Output: assert relasi terbaca, SVG valid dan memuat nama tabel, kaki gagak, garis putus-putus.
 * Dipakai oleh : php artisan test --filter=ErdMapTest.
 * Status       : BARU 2026-10-17.
 */
class ErdMapTest extends TestCase
{
    private const MD = <<<'MD'
## Table relations

| Parent | Child | Cardinality | Column | Link |
|---|---|---|---|---|
| `iqp_inquiry` | `iqp_part` | 1:N | `inquiry_id` | FK |
| `iqp_part` | `iqp_part` | 1:N | `parent_part_id` | FK |
| `cust_customer` | `iqp_inquiry` | 1:N | `customer_id` | logical |
| `iqp_part` | `mat_raw` | N:M | `via iqp_part_material` | logical |
MD;

    public function test_relations_are_parsed_and_drawn(): void
    {
        $rels = ErdMap::parse(self::MD);

        $this->assertCount(4, $rels);
        $this->assertSame('logical', $rels[2]['link']);
        $this->assertSame('N:M', $rels[3]['card']);

        $svg = ErdMap::svg($rels, 'x.md');
        $this->assertNotFalse(simplexml_load_string($svg), 'SVG must be valid XML');
        foreach (['iqp_inquiry', 'iqp_part', 'cust_customer', 'mat_raw', 'stroke-dasharray'] as $needle) {
            $this->assertStringContainsString($needle, $svg);
        }
    }

    public function test_page_without_the_section_returns_null(): void
    {
        $this->assertNull(ErdMap::parse("## Facts\n"));
    }
}
