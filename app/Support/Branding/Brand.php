<?php

namespace App\Support\Branding;

use App\Models\Sys\SysConstant;

// Titik akses tunggal ke identitas aplikasi (docs/configurationstandard.md) --
// dibaca dari tm2sys.sys_constant (APP_NAME/APP_TAGLINE/APP_LOGO_PATH/
// APP_FONT_FAMILY), diedit lewat layar Configuration. Dipakai shell lama
// (topbar, halaman auth) DAN AdminPanelProvider (Filament) -- sumber sama,
// SATU tempat ganti nama/logo/font berlaku ke semua permukaan.
class Brand
{
    public static function name(): string
    {
        return SysConstant::text('APP_NAME', 'ERP TM');
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
