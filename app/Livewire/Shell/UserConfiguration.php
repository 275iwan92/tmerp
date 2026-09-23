<?php

namespace App\Livewire\Shell;

use App\Models\Sys\SysLanguage;
use App\Models\Sys\SysTheme;
use App\Support\ActiveTheme;
use App\Support\Localization\Translator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

// Isi jendela "Configuration" di menu profil (permintaan user 2026-09-23):
// pengaturan MILIK TIAP USER (semua user), terpisah dari pengaturan tingkat
// aplikasi yang tetap di grup Settings sidebar kiri bawah (khusus admin).
// Bagian baru (mis. kerapatan grid, backlog01 §22.3) = tambah entri sections()
// + satu blok @if di view.
class UserConfiguration extends Component
{
    public string $section = 'themes';

    public function sections(): array
    {
        return [
            'themes' => ['label' => Translator::t('shell_cfg_themes', 'Themes'), 'icon' => 'fa-solid fa-palette', 'soon' => false],
            'language' => ['label' => Translator::t('shell_cfg_language', 'Language'), 'icon' => 'fa-solid fa-language', 'soon' => false],
            'grid' => ['label' => Translator::t('shell_cfg_grid', 'Grid'), 'icon' => 'fa-solid fa-table-cells', 'soon' => true],
        ];
    }

    public function setSection(string $key): void
    {
        if (array_key_exists($key, $this->sections())) {
            $this->section = $key;
        }
    }

    // Matrix 4 mode x 3 corak + Klasik, 100% dari tm2sys.sys_theme (themestandard.md).
    #[Computed]
    public function themeMatrix(): array
    {
        $themes = SysTheme::where('activate', true)->orderBy('sort_order')->get();

        $families = ['pastel' => 'Pastel', 'cerah' => 'Cerah', 'dark' => 'Dark', 'hitam' => 'Hitam'];
        $hues = [
            'blue' => Translator::t('shell_hue_blue', 'Biru'),
            'green' => Translator::t('shell_hue_green', 'Hijau'),
            'red' => Translator::t('shell_hue_red', 'Merah'),
        ];

        $rows = [];
        foreach ($families as $fKey => $fLabel) {
            $cells = [];
            foreach ($hues as $hKey => $hLabel) {
                $t = $themes->first(fn ($x) => $x->family === $fKey && $x->hue === $hKey);
                $cells[] = $t ? $this->themeVm($t) : null;
            }
            $rows[] = ['label' => $fLabel, 'cells' => $cells];
        }

        return [
            'hues' => array_values($hues),
            'rows' => $rows,
            'classic' => $themes->whereNull('family')->map(fn ($t) => $this->themeVm($t))->values()->all(),
        ];
    }

    protected function themeVm(SysTheme $t): array
    {
        return ['code' => $t->code, 'name' => $t->name, 'primary' => $t->primary_color, 'sidebar' => $t->sidebar_color, 'dark' => $t->mode === 'dark'];
    }

    public function currentThemeCode(): string
    {
        return ActiveTheme::resolve()->code;
    }

    public function setTheme(string $code): void
    {
        if (SysTheme::where('code', $code)->where('activate', true)->exists()) {
            Auth::user()->update(['theme_code' => $code]);
        }
    }

    #[Computed]
    public function languages()
    {
        $flags = ['id' => '🇮🇩', 'en' => '🇬🇧'];

        return SysLanguage::orderByDesc('is_default')->orderBy('code')->get()
            ->map(fn ($l) => ['code' => $l->code, 'name' => $l->name, 'flag' => $flags[$l->code] ?? '🌐']);
    }

    public function currentLangCode(): string
    {
        return Translator::lang();
    }

    public function setLang(string $code): void
    {
        Translator::setLang($code);
    }

    public function render()
    {
        return view('livewire.shell.user-configuration');
    }
}
