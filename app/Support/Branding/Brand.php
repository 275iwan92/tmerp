<?php

namespace App\Support\Branding;

use App\Models\Master\OrgCompany;
use App\Models\Sys\SysConstant;

// Titik akses tunggal ke identitas aplikasi (docs/configurationstandard.md) --
// dibaca dari tm2sys.sys_constant (APP_NAME/APP_TAGLINE/APP_LOGO_PATH/
// APP_FONT_FAMILY), diedit lewat layar Configuration. Dipakai shell v2
// (topbar, halaman auth) -- SATU tempat ganti nama/logo/font berlaku ke semua permukaan.
class Brand
{
    public static function name(): string
    {
        return SysConstant::text('APP_NAME', 'ERP TM');
    }

    // Nama perusahaan (keputusan user 2026-09-23): SELALU dipakai di aplikasi &
    // kop cetak. Nama terdaftar hanya pilihan "PPN" saat mencetak dokumen ber-PPN.
    public static function companyName(): string
    {
        return SysConstant::text('COMPANY_NAME', 'Telasih Makmur');
    }

    public static function companyLegalName(): string
    {
        return SysConstant::text('COMPANY_LEGAL_NAME', 'CV. Langgeng Jaya Makmur');
    }

    public static function companyNpwp(): ?string
    {
        return OrgCompany::where('company_code', 'TM')->value('npwp_number');
    }

    public static function tagline(): string
    {
        return SysConstant::text('APP_TAGLINE', '') ?? '';
    }

    public static function logoPath(): ?string
    {
        return SysConstant::text('APP_LOGO_PATH');
    }

    public static function hasLogo(): bool
    {
        return (bool) self::logoPath();
    }

    public static function logoUrl(): ?string
    {
        $path = self::logoPath();

        return $path ? asset('storage/'.$path) : null;
    }

    public static function fontKey(): string
    {
        return SysConstant::text('APP_FONT_FAMILY', 'system-ui') ?? 'system-ui';
    }

    public static function fontStack(): string
    {
        return FontPresets::stack(self::fontKey());
    }

    public static function googleFontUrl(): ?string
    {
        $param = FontPresets::googleFontParam(self::fontKey());

        return $param ? 'https://fonts.googleapis.com/css2?family='.$param.'&display=swap' : null;
    }
}
