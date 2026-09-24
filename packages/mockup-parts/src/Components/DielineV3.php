<?php

namespace MockupParts\Components;

use Devlib\Components\DevlibComponent;

// Dieline v3 = v2 + judul spesifikasi (TIPE · L × W × H MM), panjang total Cutting / Creasing / Perforation
// (dasar meter lari pisau), dan jendela kecil 3D statis (kotak isometrik / silinder / lembaran).
class DielineV3 extends DielineV2
{
    public function tag(): string
    {
        return 'x-mp::dieline-v3';
    }

    public function level(): int
    {
        return 3;
    }
}
