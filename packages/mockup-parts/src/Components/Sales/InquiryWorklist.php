<?php

namespace MockupParts\Components\Sales;

use Devlib\Components\Grid;

// Worklist Inquiry Product = Grid Transactional DevLib (tampilan sama persis dengan Quotation / Customer Order,
// user 2026-10-01) + aturan khas (JS InquiryWorklist):
//   - buka baris ke layar sesuai jenis dokumen (NEW / REVISE / RECALC = 3 layar terpisah, CLAUDE.md §4),
//     alamatnya dari atribut data-open='{"NEW":"/s/inquiry-new?id={id}", ...}'; DRAFT = edit, status lain lihat-saja;
//   - + New Record membuka wizard jenis inquiry (atribut data-new-modal), bukan pindah layar;
//   - Duplicate ke customer lain per baris.
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
        [$this->actions, $this->activeKey] = ['worklist', null];

        return parent::render();
    }
}
