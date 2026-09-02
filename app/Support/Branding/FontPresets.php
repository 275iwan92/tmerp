<?php

namespace App\Support\Branding;

// Preset font aplikasi (docs/configurationstandard.md §2) -- daftar TERBATAS
// (bukan input bebas) supaya nilai APP_FONT_FAMILY di sys_constant selalu
// resolvable ke CSS var + link Google Fonts yang benar. 'system-ui' = default,
// TANPA request eksternal (font asli OS/browser) -- opsi lain butuh koneksi ke
// fonts.googleapis.com saat halaman dimuat.
class FontPresets
{
    private const PRESETS = [
        'system-ui' => [
            'label' => 'Bawaan Sistem (tanpa font eksternal)',
            'stack' => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif",
            'google' => null,
        ],
        'inter' => [
            'label' => 'Inter',
            'stack' => "'Inter', -apple-system, sans-serif",
            'google' => 'Inter:wght@400;500;600;700',
        ],
        'poppins' => [
            'label' => 'Poppins',
            'stack' => "'Poppins', -apple-system, sans-serif",
            'google' => 'Poppins:wght@400;500;600;700',
        ],
        'nunito' => [
            'label' => 'Nunito',
            'stack' => "'Nunito', -apple-system, sans-serif",
            'google' => 'Nunito:wght@400;600;700',
        ],
        'roboto' => [
            'label' => 'Roboto',
            'stack' => "'Roboto', -apple-system, sans-serif",
            'google' => 'Roboto:wght@400;500;700',
        ],
    ];

    public static function labels(): array
    {
        return collect(self::PRESETS)->map(fn ($p) => $p['label'])->all();
    }

    public static function stack(string $key): string
    {
        return self::PRESETS[$key]['stack'] ?? self::PRESETS['system-ui']['stack'];
    }

    public static function googleFontParam(string $key): ?string
    {
        return self::PRESETS[$key]['google'] ?? null;
    }
}
