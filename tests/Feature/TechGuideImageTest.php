<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use Tests\TestCase;

/*
 * REMARK
 * Tujuan       : Menjaga rute gambar Technical Guide: SVG ada disajikan sebagai image/svg+xml, nama tidak dikenal atau berbahaya = 404.
 * Sumber hulu  : routes/v2_techguide.php, docs/tg/sales/img/costing-advisor.svg.
 * Input        : GET /v2/tg-img/{name} sebagai admin. Output: assert status dan tipe isi.
 * Dipakai oleh : php artisan test --filter=TechGuideImageTest.
 * Status       : BARU 2026-10-16.
 */
class TechGuideImageTest extends TestCase
{
    public function test_svg_is_served_and_unknown_or_unsafe_names_are_404(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());

        $this->get('/v2/tg-img/costing-advisor')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->get('/v2/tg-img/no-such-diagram')->assertNotFound();
        $this->get('/v2/tg-img/..%2F..%2F.env')->assertNotFound();
    }
}
