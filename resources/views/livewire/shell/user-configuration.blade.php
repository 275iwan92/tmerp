@php use App\Support\Localization\Translator; @endphp
{{-- Isi jendela Configuration (App\Livewire\Shell\UserConfiguration). Tema &
     bahasa berlaku saat halaman dimuat ulang (theme-vars.blade.php); jendela
     dibuka lagi otomatis lewat sessionStorage tmWinReopen (ui-window.js). --}}
<div class="tm-cfg">
    <nav class="tm-cfg-nav">
        @foreach ($this->sections() as $key => $s)
            <button type="button" wire:key="cfg-nav-{{ $key }}" wire:click="setSection('{{ $key }}')"
                class="tm-cfg-nav-item {{ $section === $key ? 'on' : '' }}">
                <i class="{{ $s['icon'] }}"></i><span>{{ $s['label'] }}</span>
                @if ($s['soon'])<em>{{ Translator::t('shell_cfg_soon', 'segera') }}</em>@endif
            </button>
        @endforeach
    </nav>

    <section class="tm-cfg-main">
        @if ($section === 'themes')
            <h4>{{ Translator::t('shell_cfg_themes', 'Themes') }}</h4>
            <p class="tm-cfg-hint">{{ Translator::t('shell_cfg_themes_hint', 'Pilih tampilan aplikasi untuk akun Anda sendiri. Tidak mengubah tampilan user lain.') }}</p>
            <div class="tm-cfg-matrix">
                <span></span>
                @foreach ($this->themeMatrix['hues'] as $h)<span class="tm-cfg-colhead">{{ $h }}</span>@endforeach
                @foreach ($this->themeMatrix['rows'] as $row)
                    <span class="tm-cfg-rowhead">{{ $row['label'] }}</span>
                    @foreach ($row['cells'] as $cell)
                        @if ($cell)
                            <button type="button" wire:key="th-{{ $cell['code'] }}" title="{{ $cell['name'] }}"
                                class="tm-cfg-theme {{ $cell['code'] === $this->currentThemeCode() ? 'on' : '' }}"
                                @click="sessionStorage.setItem('tmWinReopen', 'config'); $wire.setTheme('{{ $cell['code'] }}').then(() => location.reload())">
                                <span class="tm-cfg-swatch"><i style="background:{{ $cell['sidebar'] }}"></i><b style="background:{{ $cell['primary'] }}"></b></span>
                                <small>{{ $cell['name'] }}</small>
                            </button>
                        @else
                            <span></span>
                        @endif
                    @endforeach
                @endforeach
            </div>
            @if ($this->themeMatrix['classic'])
                <h5>{{ Translator::t('shell_classic', 'Klasik') }}</h5>
                <div class="tm-cfg-classic">
                    @foreach ($this->themeMatrix['classic'] as $t)
                        <button type="button" wire:key="thc-{{ $t['code'] }}" title="{{ $t['name'] }}"
                            class="tm-cfg-theme {{ $t['code'] === $this->currentThemeCode() ? 'on' : '' }}"
                            @click="sessionStorage.setItem('tmWinReopen', 'config'); $wire.setTheme('{{ $t['code'] }}').then(() => location.reload())">
                            <span class="tm-cfg-swatch"><i style="background:{{ $t['sidebar'] }}"></i><b style="background:{{ $t['primary'] }}"></b></span>
                            <small>{{ $t['name'] }}</small>
                        </button>
                    @endforeach
                </div>
            @endif
        @endif

        @if ($section === 'language')
            <h4>{{ Translator::t('shell_cfg_language', 'Language') }}</h4>
            <p class="tm-cfg-hint">{{ Translator::t('shell_cfg_language_hint', 'Bahasa teks aplikasi untuk akun Anda. Layar yang belum diterjemahkan tetap tampil dalam bahasa Indonesia.') }}</p>
            <div class="tm-cfg-langs">
                @foreach ($this->languages as $lang)
                    <button type="button" wire:key="lang-{{ $lang['code'] }}"
                        class="tm-cfg-lang {{ $lang['code'] === $this->currentLangCode() ? 'on' : '' }}"
                        @click="sessionStorage.setItem('tmWinReopen', 'config'); $wire.setLang('{{ $lang['code'] }}').then(() => location.reload())">
                        <span class="flag">{{ $lang['flag'] }}</span><span>{{ $lang['name'] }}</span>
                        @if ($lang['code'] === $this->currentLangCode())<i class="fa-solid fa-circle-check"></i>@endif
                    </button>
                @endforeach
            </div>
        @endif

        @if ($section === 'grid')
            <h4>{{ Translator::t('shell_cfg_grid', 'Grid') }} <em class="tm-cfg-soon">{{ Translator::t('shell_cfg_soon', 'segera') }}</em></h4>
            <p class="tm-cfg-hint">{{ Translator::t('shell_cfg_grid_hint', 'Rencana: satu setelan tampilan tabel untuk semua grid, tersimpan di akun Anda. Belum aktif — pratinjau saja.') }}</p>
            <div class="tm-cfg-soonbox">
                <div class="lbl">{{ Translator::t('shell_cfg_grid_density', 'Kerapatan baris') }}</div>
                <div class="opts"><span>Rapat</span><span class="on">Normal</span><span>Longgar</span></div>
                <div class="lbl">{{ Translator::t('shell_cfg_grid_lines', 'Baris per sel') }}</div>
                <div class="opts"><span>Otomatis</span><span class="on">Tetap 1</span></div>
            </div>
        @endif
    </section>
</div>
