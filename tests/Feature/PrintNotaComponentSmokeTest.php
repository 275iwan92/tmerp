<?php

namespace Tests\Feature;

use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sys\SecUser;
use Tests\TestCase;

// docs/printnotastandard.md -- verifikasi refactor inquiry/nota.blade.php dan
// quotation/print.blade.php ke resources/views/components/print/* (2026-08-28)
// tidak menghilangkan konten kunci (kop, parties, section, tanda tangan, footer).
class PrintNotaComponentSmokeTest extends TestCase
{
    // Cetak Quotation sekarang v2 (x-dl::print-doc); v1 pages::quotation.print diarsipkan 2026-10-20.
    public function test_quotation_print_renders_v2_print_doc(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());

        $quotation = QuoQuotation::where('doc_status', 'PUBLISHED')->firstOrFail();

        $html = $this->get('/v2/print/quotation/'.$quotation->id_quotation)->assertOk()->getContent();

        $this->assertStringContainsString('dl-pdoc', $html);
        $this->assertStringContainsString('dl-pdoc__kop', $html);
        $this->assertStringContainsString($quotation->quotation_no, $html);
        $this->assertStringContainsString('dl-pdoc__sign', $html);
        $this->assertStringContainsString('dl-pdoc__foot', $html);
    }

    // Nota Inquiry sekarang v2 (x-dl::print-doc, A4 dengan lembar lampiran); v1 pages::inquiry.nota diarsipkan 2026-10-20.
    public function test_inquiry_note_renders_v2_print_doc_with_appendices(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());

        $inquiry = IqpInquiry::has('parts')->firstOrFail();

        $html = $this->get('/v2/print/inquiry-note/'.$inquiry->id_inquiry)->assertOk()->getContent();

        $this->assertStringContainsString('dl-pdoc', $html);
        $this->assertStringContainsString('data-paper="a4"', $html);
        $this->assertStringContainsString($inquiry->inquiry_no, $html);
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'dl-pdoc__sheet is-a4 dl-pdoc__appx'), 'Specification + Terms & Customer Confirmation sheets.');
        $this->assertStringContainsString('dl-pdoc__chk', $html, 'Customer confirmation checkboxes.');
    }
}
