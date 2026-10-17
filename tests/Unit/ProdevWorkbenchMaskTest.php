<?php

/* REMARK
   Tujuan      : menjaga perbaikan imposisi kosong pada blank besar (Rinal 99,7 cm, 2026-10-18).
   Sumber hulu : packages/mockup-parts/.../prodev/workbench.js (MASK_MAX, guard autoNest).
   Input       : teks sumber workbench.js.
   Output      : lulus/gagal.
   Dipakai oleh: php artisan test --filter=ProdevWorkbenchMaskTest.
   Status      : aktif. */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// Susunan membaca piksel mask sebagai 0,1 mm; mask yang diperkecil membuat blank > 70 cm salah muat.
class ProdevWorkbenchMaskTest extends TestCase
{
    private function src(): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/packages/mockup-parts/resources/assets/js/components/prodev/workbench.js');
    }

    public function test_mask_max_covers_the_largest_machine_bed(): void
    {
        $this->assertSame(1, preg_match('/const MASK_MAX = (\d+);/', $this->src(), $m));
        $this->assertGreaterThanOrEqual(1030, (int) $m[1], 'bed terbesar 103 cm = 1030 px');
    }

    public function test_auto_nest_refuses_a_downscaled_mask(): void
    {
        $this->assertStringContainsString('base.mmPerPx > 1.01', $this->src());
    }
}
