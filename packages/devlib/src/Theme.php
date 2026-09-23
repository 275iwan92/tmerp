<?php

namespace Devlib;

// Sumber data tema (salinan tm2sys.sys_theme: 4 keluarga x 3 corak = 12 tema).
// Di tmerp nanti dibaca dari tabel; di situs DevLib (tanpa DB) dari konstanta ini.
// Tema hanya MENIMPA token warna di :root (devlib.css) -- komponen tidak tahu tema.
final class Theme
{
    public const DEFAULT = 'navy';

    // code => [nama, keluarga, corak, warna utama, warna sidebar, latar isi]
    public const ALL = [
        'pastel_blue' => ['Pastel Biru', 'pastel', 'blue', '#6aa3e8', '#1f2742', '#f2f6fc'],
        'pastel_green' => ['Pastel Hijau', 'pastel', 'green', '#7fae8f', '#1c2e26', '#f2f7f3'],
        'pastel_red' => ['Pastel Merah', 'pastel', 'red', '#d98fa0', '#2f2028', '#faf3f5'],
        'navy' => ['Cerah Biru', 'cerah', 'blue', '#2f6fed', '#1f2742', null],
        'emerald' => ['Cerah Hijau', 'cerah', 'green', '#16a34a', '#123a26', null],
        'cerah_red' => ['Cerah Merah', 'cerah', 'red', '#e5484d', '#3a1414', null],
        'dark_blue' => ['Dark Biru', 'dark', 'blue', '#4c85ff', '#101b30', '#12182c'],
        'dark_green' => ['Dark Hijau', 'dark', 'green', '#34d399', '#0b1f16', '#0f1c17'],
        'dark_red' => ['Dark Merah', 'dark', 'red', '#ff6b6b', '#2a1416', '#1f1518'],
        'hitam_blue' => ['Hitam Biru', 'hitam', 'blue', '#5b9dff', '#0a0a0c', '#060608'],
        'hitam_green' => ['Hitam Hijau', 'hitam', 'green', '#2ee6a6', '#0a0a0c', '#060608'],
        'hitam_red' => ['Hitam Merah', 'hitam', 'red', '#ff5c5c', '#0a0a0c', '#060608'],
    ];

    public const FAMILIES = ['pastel' => 'Pastel', 'cerah' => 'Cerah', 'dark' => 'Dark', 'hitam' => 'Hitam'];

    public const HUES = ['blue' => 'Biru', 'green' => 'Hijau', 'red' => 'Merah'];

    // Permukaan gelap untuk keluarga dark/hitam (token netral ikut berubah).
    private const DARK = [
        'dark' => ['surface' => '#1a2238', 'surface-alt' => '#161d31', 'hover' => '#222b45', 'border' => '#2b3552', 'ink' => '#d6dcef', 'ink-strong' => '#f1f4ff', 'muted' => '#8a93b3'],
        'hitam' => ['surface' => '#111114', 'surface-alt' => '#0c0c0f', 'hover' => '#1a1a1f', 'border' => '#26262d', 'ink' => '#d4d4dc', 'ink-strong' => '#f4f4f8', 'muted' => '#8b8b96'],
    ];

    /** Token CSS milik satu tema. */
    public static function tokens(string $code): array
    {
        // Tema bawaan = nilai :root di devlib.css (persis mockup), tidak ditimpa.
        if ($code === self::DEFAULT) {
            return [];
        }
        [, $family, , $primary, $sidebar, $bg] = self::ALL[$code] ?? self::ALL[self::DEFAULT];
        $t = [
            'accent' => $primary,
            'accent-2' => "color-mix(in srgb, $primary 82%, #000)",
            'accent-soft' => "color-mix(in srgb, $primary 12%, var(--dl-surface))",
            'accent-soft-2' => "color-mix(in srgb, $primary 20%, var(--dl-surface))",
            'accent-ring' => "color-mix(in srgb, $primary 15%, transparent)",
            'side' => $sidebar, 'navy' => $sidebar, 'navy-2' => "color-mix(in srgb, $sidebar 80%, #fff)",
            'topbar' => "color-mix(in srgb, $sidebar 70%, #000)",
        ];
        if ($bg) {
            $t['bg'] = $bg;
        }
        if (isset(self::DARK[$family])) {
            $d = self::DARK[$family];
            $t += [
                'surface' => $d['surface'], 'surface-alt' => $d['surface-alt'], 'surface-muted' => $d['hover'], 'hover' => $d['hover'],
                'border' => $d['border'], 'border-soft' => $d['border'], 'border-hover' => $d['muted'],
                'ink' => $d['ink'], 'ink-strong' => $d['ink-strong'], 'muted' => $d['muted'],
                'chip' => $d['hover'], 'tabbar' => $d['surface-alt'], 'tab' => $d['hover'], 'tab-hover' => $d['border'], 'tab-ink' => $d['muted'],
                'f-ink' => $d['ink-strong'], 'f-label' => $d['ink'], 'f-muted' => $d['muted'], 'f-line' => $d['border'], 'f-line-soft' => $d['border'], 'f-bg-soft' => $d['hover'],
                'info-soft' => "color-mix(in srgb, $primary 14%, {$d['surface']})", 'info-line' => "color-mix(in srgb, $primary 35%, {$d['surface']})",
                'danger-soft' => '#3a1d20', 'danger-line' => '#5c2a2e', 'code-bg' => $d['border'],
            ];
        }

        return $t;
    }

    public static function css(): string
    {
        $out = '';
        foreach (array_keys(self::ALL) as $code) {
            $vars = '';
            foreach (self::tokens($code) as $k => $v) {
                $vars .= "--dl-$k:$v;";
            }
            $out .= "[data-dl-theme=\"$code\"]{{$vars}}\n";
        }

        return $out;
    }
}
