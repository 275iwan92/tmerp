<?php

namespace MockupParts\Components\Sales;

use Devlib\Components\Grid;

// Worklist Inquiry Product = Grid worklist DevLib + 2 aturan khas (JS InquiryWorklist):
//   - buka baris ke layar sesuai jenis dokumen (NEW / REVISE / RECALC = 3 layar terpisah, CLAUDE.md §4),
//     alamatnya dari atribut data-open='{"NEW":"/s/inquiry-new?id={id}", ...}';
//   - DRAFT dibuka untuk diedit (ikon pensil), status lain lihat-saja (ikon panah), tanpa tombol Confirm di grid.
class InquiryWorklist extends Grid
{
    public function tag(): string
    {
        return 'x-mp::sales.inquiry-worklist';
    }

    public function jsClass(): ?string
    {
        return 'InquiryWorklist';
    }

    // Preset worklist (dipasang sebelum data komponen diambil untuk tampilan).
    public function render()
    {
        [$this->actions, $this->activeKey, $this->newRecord] = ['worklist', null, null];

        return parent::render();
    }
}
