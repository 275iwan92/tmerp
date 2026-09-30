<?php

namespace Tests\Unit;

use App\Support\Dieline\TmDieline;
use PHPUnit\Framework\TestCase;

// Port PHP renderer dieline (App\Support\Dieline\TmDieline) harus sama dengan renderer JS tmdieline.js.
// Kesamaan penuh 27 tipe x 2 ukuran sudah dicek terhadap node (2026-10-01); test ini menjaga angka kuncinya.
class TmDielineTest extends TestCase
{
    // Setengah RSC (RSC_2PC) seperti migration 2026_09_30_000012, cukup elemen yang relevan.
    private const RSC = [
        'vars' => ['W' => 'G+A+B', 'H' => 'C+B+2*flap_extra', 'x1' => 'G', 'yT' => 'B/2+flap_extra', 'yB' => 'B/2+flap_extra+C', 'ga' => 'min(10, G/3)'],
        'flat' => ['w' => 'G+A+B+2*P', 'h' => 'C+B+2*flap_extra+2*P'],
        'canvas' => ['w' => 'W', 'h' => 'H'],
        'elements' => [
            ['t' => 'line', 'k' => 'CREASE', 'x1' => 'x1', 'y1' => 'yT', 'x2' => 'x1', 'y2' => 'yB'],
            ['t' => 'glue', 'k' => 'CUT', 'x0' => '0', 'x1' => 'x1', 'yT' => 'yT', 'yB' => 'yB', 'g' => 'ga'],
            ['t' => 'tuck', 'k' => 'CUT', 'a' => '0', 'b' => 'A', 'y' => 'yT', 'h' => '15', 'dir' => 'up'],
        ],
    ];

    public function test_flat_matches_knife_size_of_real_master_cards(): void
    {
        // KS Power N120 (pisau 72,9 x 46,4 cm), Osima N100 (62,3 x 45,2), YAMA Din170 (76,5 x 49,4).
        foreach ([[507, 187, 267, 35, 5, 729, 464], [413, 180, 260, 30, 6, 623, 452], [515, 220, 257, 30, 8.5, 765, 494]] as [$l, $w, $h, $g, $e, $fw, $fh]) {
            $flat = TmDieline::flat(self::RSC, ['product_length' => $l, 'product_width' => $w, 'product_height' => $h, 'glue_flap' => $g, 'flap_extra' => $e, 'overflow_margin' => 0]);
            $this->assertSame([(float) $fw, (float) $fh], [$flat['w'], $flat['h']]);
        }
    }

    public function test_svg_output_is_identical_to_js_renderer(): void
    {
        $svg = TmDieline::svg(self::RSC, ['product_length' => 507, 'product_width' => 187, 'product_height' => 267, 'glue_flap' => 35, 'flap_extra' => 5]);

        // String hasil TMDieline.svg() di node untuk spec & ukuran yang sama.
        $this->assertSame('<svg width="100%" height="100%" viewBox="-74.56 -74.56 878.13 613.13" xmlns="http://www.w3.org/2000/svg">'
            .'<line x1="35" y1="98.5" x2="35" y2="365.5" fill="none" stroke="#00e676" stroke-width="1"/>'
            .'<path d="M35,98.5 L0,108.5 L0,355.5 L35,365.5" fill="none" stroke="#ff3b3b" stroke-width="1"/>'
            .'<path d="M0,98.5 L0,91.5 Q0,83.5 8,83.5 L499,83.5 Q507,83.5 507,91.5 L507,98.5" fill="none" stroke="#ff3b3b" stroke-width="1"/></svg>', $svg);
    }

    public function test_expression_evaluator(): void
    {
        $s = ['A' => 10.0, 'B' => 4.0];
        $this->assertSame(2.0, TmDieline::eval('-A+3*B', $s));
        $this->assertSame(7.0, TmDieline::eval('max(A/2, B+3)', $s));
        $this->assertSame(0.0, TmDieline::eval('A/0', $s));
        $this->assertSame(0.0, TmDieline::eval('UNKNOWN*2', $s));
    }
}
