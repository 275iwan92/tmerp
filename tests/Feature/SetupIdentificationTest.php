<?php

namespace Tests\Feature;

use App\Models\Sys\SysConstant;
use App\Support\V2\Data\Identification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Setup > Identification v2 + teks halaman masuk dari kamus utama (C1, docs/menu_tm2_belum_v2.md).
class SetupIdentificationTest extends TestCase
{
    public function test_app_identity_roundtrip_keeps_values(): void
    {
        $before = Identification::app();
        Identification::saveApp(['app_name' => $before['app_name'], 'app_tagline' => $before['app_tagline'], 'app_font' => $before['app_font']], null, 'test');

        $this->assertEquals($before, Identification::app());
        $this->assertSame($before['app_name'], SysConstant::text('APP_NAME'));
    }

    public function test_unknown_font_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        Identification::saveApp(['app_name' => 'X', 'app_tagline' => '', 'app_font' => 'comic-sans'], null, 'test');
    }

    public function test_company_name_is_required(): void
    {
        $this->expectException(ValidationException::class);
        Identification::saveCompany(['name' => '', 'legal_name' => 'X'], null, null, 'test');
    }
}
