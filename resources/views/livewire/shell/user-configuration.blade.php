{{-- Isi drawer Configuration (App\Livewire\Shell\UserConfiguration). Tema &
     bahasa berlaku saat halaman dimuat ulang (theme-vars.blade.php); drawer
     dibuka lagi otomatis lewat sessionStorage tmDrawerReopen (profileDrawers di app-shell.js). --}}
<div class="tm-cfg">
    <nav class="tm-cfg-nav">
        @foreach ($this->sections() as $key => $s)
            <button type="button" wire:key="cfg-nav-{{ $key }}" wire:click="setSection('{{ $key }}')"
                class="tm-cfg-nav-item {{ $section === $key ? 'on' : '' }}">
                <i class="{{ $s['icon'] }}"></i><span>{{ $s['label'] }}</span>
            </button>
        @endforeach
    </nav>

    <section class="tm-cfg-main">
        @if ($section === 'themes')
            <h4>{{ t('Themes') }}</h4>
            <p class="tm-cfg-hint">{{ t('Choose the application look for your own account. Other users are not affected.') }}</p>
            <div class="tm-cfg-matrix">
                <span></span>
                @foreach ($this->themeMatrix['hues'] as $h)<span class="tm-cfg-colhead">{{ $h }}</span>@endforeach
                @foreach ($this->themeMatrix['rows'] as $row)
                    <span class="tm-cfg-rowhead">{{ $row['label'] }}</span>
                    @foreach ($row['cells'] as $cell)
                        @if ($cell)
                            <button type="button" wire:key="th-{{ $cell['code'] }}" title="{{ $cell['name'] }}"
                                class="tm-cfg-theme {{ $cell['code'] === $this->currentThemeCode() ? 'on' : '' }}"
                                @click="sessionStorage.setItem('tmDrawerReopen', 'configOpen'); $wire.setTheme('{{ $cell['code'] }}').then(() => location.reload())">
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
                <h5>{{ t('Classic') }}</h5>
                <div class="tm-cfg-classic">
                    @foreach ($this->themeMatrix['classic'] as $t)
                        <button type="button" wire:key="thc-{{ $t['code'] }}" title="{{ $t['name'] }}"
                            class="tm-cfg-theme {{ $t['code'] === $this->currentThemeCode() ? 'on' : '' }}"
                            @click="sessionStorage.setItem('tmDrawerReopen', 'configOpen'); $wire.setTheme('{{ $t['code'] }}').then(() => location.reload())">
                            <span class="tm-cfg-swatch"><i style="background:{{ $t['sidebar'] }}"></i><b style="background:{{ $t['primary'] }}"></b></span>
                            <small>{{ $t['name'] }}</small>
                        </button>
                    @endforeach
                </div>
            @endif
        @endif

        @if ($section === 'language')
            <h4>{{ t('Language') }}</h4>
            <p class="tm-cfg-hint">{{ t('Application text language for your account. Text without a translation is shown in English.') }}</p>
            <div class="tm-cfg-langs">
                @foreach ($this->languages as $lang)
                    <button type="button" wire:key="lang-{{ $lang['code'] }}"
                        class="tm-cfg-lang {{ $lang['code'] === $this->currentLangCode() ? 'on' : '' }}"
                        @click="sessionStorage.setItem('tmDrawerReopen', 'configOpen'); $wire.setLang('{{ $lang['code'] }}').then(() => location.reload())">
                        <span class="flag">{{ $lang['flag'] }}</span><span>{{ $lang['name'] }}</span>
                        @if ($lang['code'] === $this->currentLangCode())<i class="fa-solid fa-circle-check"></i>@endif
                    </button>
                @endforeach
            </div>
        @endif
    </section>
</div>
