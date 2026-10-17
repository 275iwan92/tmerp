<?php

namespace Tests\Unit;

use App\Support\Docs\RwMap;
use PHPUnit\Framework\TestCase;

/*
 * REMARK
 * Tujuan       : Menjaga pembaca tabel Read/Write map dan pembangun SVG matriksnya (App\Support\Docs\RwMap).
 * Sumber hulu  : App\Support\Docs\RwMap.
 * Input        : Teks md contoh dengan satu tabel. Output: assert proses, tabel baca dan tulis, SVG memuat kolom dan jenis tulis.
 * Dipakai oleh : php artisan test --filter=RwMapTest.
 * Status       : BARU 2026-10-17.
 */
class RwMapTest extends TestCase
{
    private const MD = <<<'MD'
# Sub-module

## Read/Write map

Intro.

| Process | Trigger | Reads | Writes | Evidence |
|---|---|---|---|---|
| `Save` | Screen, Save | `prod_type` | `iqp_inquiry` (U), `iqp_tier` (CUD) | `app/Services/Sales/InquiryWriter.php:27-55` |
| `Open` | Screen | `iqp_inquiry` | - | - |

## Next section
MD;

    public function test_table_is_parsed_and_matrix_marks_reads_and_writes(): void
    {
        $rows = RwMap::parse(self::MD);

        $this->assertCount(2, $rows);
        $this->assertSame(['prod_type'], $rows[0]['reads']);
        $this->assertSame(['iqp_inquiry' => 'U', 'iqp_tier' => 'CUD'], $rows[0]['writes']);
        $this->assertSame([], $rows[1]['writes']);

        $svg = RwMap::svg($rows, 'x.md');
        $this->assertStringContainsString('iqp_tier', $svg);
        $this->assertStringContainsString('CUD', $svg);
        $this->assertNotFalse(simplexml_load_string($svg), 'SVG must be valid XML');
    }

    public function test_page_without_the_section_returns_null(): void
    {
        $this->assertNull(RwMap::parse("# Sub-module\n\n## Facts\n"));
    }

    public function test_tables_only_read_by_one_process_are_folded_into_other_reads(): void
    {
        $rows = RwMap::parse(self::MD);
        $svg = RwMap::svg($rows, 'x.md');

        $this->assertStringContainsString('other reads', $svg);
        $this->assertStringNotContainsString('>prod_type<', $svg, 'prod_type is read by one process only, so it is folded');
        $this->assertStringContainsString('>iqp_tier<', $svg, 'written tables keep their own column');
        $this->assertNotFalse(simplexml_load_string($svg));
    }
}
