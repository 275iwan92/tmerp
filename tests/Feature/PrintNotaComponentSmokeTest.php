<?php

namespace Tests\Feature;

use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sys\SecUser;
use Livewire\Livewire;
use Tests\TestCase;

// docs/printnotastandard.md -- verifikasi refactor inquiry/nota.blade.php dan
// quotation/print.blade.php ke resources/views/components/print/* (2026-08-28)
// tidak menghilangkan konten kunci (kop, parties, section, tanda tangan, footer).
class PrintNotaComponentSmokeTest extends TestCase
{
    public function test_quotation_print_renders_shared_print_components(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $quotation = QuoQuotation::where('doc_status', 'PUBLISHED')->firstOrFail();

        $html = Livewire::test('pages::quotation.print', ['quotation' => $quotation->id_quotation])->html();

        $this->assertStringContainsString('print-doc', $html);
        $this->assertStringContainsString('print-kop', $html);
        $this->assertStringContainsString('SURAT PENAWARAN HARGA', $html);
        $this->assertStringContainsString($quotation->quotation_no, $html);
        $this->assertStringContainsString('print-parties', $html);
        $this->assertStringContainsString('KEPADA YTH.', $html);
        $this->assertStringContainsString('print-sign', $html);
        $this->assertStringContainsString('PENGESAHAN', $html);
        $this->assertStringContainsString('print-foot', $html);
    }

    public function test_inquiry_nota_renders_shared_print_components_with_bw_toggle(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $inquiry = IqpInquiry::has('parts')->firstOrFail();

        $component = Livewire::test('pages::inquiry.nota', ['inquiry' => $inquiry->id_inquiry]);
        $html = $component->html();

        $this->assertStringContainsString('print-doc', $html);
        $this->assertDoesNotMatchRegularExpression('/class="[^"]*\bbw\b[^"]*"/', $html);
        $this->assertStringContainsString('NOTA INQUIRY', $html);
        $this->assertStringContainsString($inquiry->inquiry_no, $html);
        $this->assertStringContainsString('KONFIRMASI CUSTOMER', $html);

        $bwHtml = $component->set('bw', true)->html();
        $this->assertMatchesRegularExpression('/class="[^"]*\bbw\b[^"]*print-doc[^"]*"|class="[^"]*print-doc[^"]*\bbw\b[^"]*"/', $bwHtml);
    }
}
