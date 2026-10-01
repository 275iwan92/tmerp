<?php

namespace App\Support\V2\Grid\Sales;

// Inquiry > Recalc: meja Sales untuk inquiry jenis RECALC (dipakai layar v2 inquiry-recalc). Setelah Confirm Sales
// dokumen langsung muncul di Pro-Cost > Recalc, tanpa Pro-Dev.
class InquiryRecalcGrid extends InquiryReviseGrid
{
    protected function type(): string
    {
        return 'RECALC';
    }
}
