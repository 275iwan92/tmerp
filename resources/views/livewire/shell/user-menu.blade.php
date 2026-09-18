@php use App\Support\Localization\Translator; @endphp
{{--
    Dropdown avatar/user -- SATU implementasi dipakai main-menu.blade.php
    (Menu Operasional, $standalone=false, andalkan ddOpen/configOpen/
    openTabs/activeTabCode/openTab() dari x-data root .shell yg SUDAH ADA)
    dan layouts/report.blade.php (Laporan Manajemen, $standalone=true,
    dibungkus x-data lokal sendiri di sana krn tidak ada tab MDI/panel).

    Panel drawer/modal di KEDUA shell dirender App\Livewire\Shell\UserPanels.
    $standalone (Laporan) hanya beda utk item yg di Operasional membuka tab/
    halaman penuh (Aktivitas saya, Pengaturan, My Security): dibuka di modal
    halaman (pageUrl), user tetap di mode Laporan.
--}}
<div class="pop">
    <button type="button" class="avatar-btn" @click.stop="ddOpen = !ddOpen" title="{{ auth()->user()->name }}">
        <span class="avatar">
            <svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5 0-9 2.5-9 6v1h18v-1c0-3.5-4-6-9-6z"/></svg>
            <span class="av-dot" :class="{ hide: {{ $this->unreadChatCount + $this->unreadNotifCount }} === 0 }"></span>
        </span>
        <i class="fa-solid fa-chevron-down" style="font-size:10px;color:#9aa3c2"></i>
    </button>
    <div class="pop-menu" :class="{ open: ddOpen }" @click.stop>
        <div class="um-head">
            <span class="um-av"><svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5 0-9 2.5-9 6v1h18v-1c0-3.5-4-6-9-6z"/></svg></span>
            <div>
                <div class="um-name">{{ auth()->user()->name ?: Translator::t('shell_user_fallback_name', 'Pengguna') }}</div>
                <div class="um-mail">{{ auth()->user()->email }}</div>
                <span class="um-role">{{ $this->roleLabel() }}</span>
            </div>
        </div>
        <div class="um-list">
            @foreach ($this->userMenuItems as $section => $items)
                <div class="um-cap">{{ match ($section) { 'Pesan & Info' => Translator::t('shell_section_pesan_info', 'Pesan & info'), 'Akun' => Translator::t('shell_section_akun', 'Akun'), default => $section } }}</div>
                @foreach ($items as $item)
                    @switch($item->code)
                        @case('UM_HELP')
                            @if ($standalone)
                                <a href="{{ route('help.index') }}" target="_blank" class="um-item" title="{{ Translator::t('shell_help_f1_hint', 'Jalan pintas: tekan F1 di mana saja') }}">
                                    <i class="{{ $item->icon }}"></i> {{ $item->label }} <span style="margin-left:auto;font-size:10px;opacity:.55;border:1px solid currentColor;border-radius:4px;padding:1px 4px">F1</span>
                                </a>
                            @else
                                <button type="button" class="um-item" title="{{ Translator::t('shell_help_f1_hint', 'Jalan pintas: tekan F1 di mana saja') }}"
                                    @click="ddOpen = false; window.open('{{ route('help.index') }}?ctx=' + encodeURIComponent(openTabs.find(t => t.code === activeTabCode)?.label || 'Home'), '_blank')">
                                    <i class="{{ $item->icon }}"></i> {{ $item->label }} <span style="margin-left:auto;font-size:10px;opacity:.55;border:1px solid currentColor;border-radius:4px;padding:1px 4px">F1</span>
                                </button>
                            @endif
                            @break
                        @case('UM_MYSECURITY')
                            @if ($standalone)
                                <button type="button" class="um-item" @click="ddOpen = false; pageTitle = @js($item->label); pageUrl = @js($this->standalonePageUrl($item))"><i class="{{ $item->icon }}"></i> {{ $item->label }}</button>
                            @else
                                <a href="{{ route('self-service.index', ['tab' => 'security']) }}" class="um-item"><i class="{{ $item->icon }}"></i> {{ $item->label }}</a>
                            @endif
                            @break
                        @default
                            @if ($standalone && $item->action_type === 'menu')
                                @if ($this->standalonePageUrl($item))
                                    <button type="button" class="um-item" @click="ddOpen = false; pageTitle = @js($item->label); pageUrl = @js($this->standalonePageUrl($item))">
                                        <i class="{{ $item->icon }}"></i> {{ $item->label }}
                                    </button>
                                @endif
                            @else
                                <button type="button" class="um-item"
                                    @click="ddOpen = false; {{ $item->action_type === 'menu' ? "openTab('".$item->action_target."')" : $item->action_target.' = true' }}">
                                    <i class="{{ $item->icon }}"></i> {{ $item->label }}
                                    @if ($item->badge_source === 'chat' && $this->unreadChatCount > 0)<span class="um-count">{{ $this->unreadChatCount }}</span>@endif
                                    @if ($item->badge_source === 'notif' && $this->unreadNotifCount > 0)<span class="um-count">{{ $this->unreadNotifCount }}</span>@endif
                                </button>
                            @endif
                    @endswitch
                @endforeach
                <div class="um-sep"></div>
            @endforeach

            <button type="button" class="um-item" @click.stop="configOpen = !configOpen">
                <i class="fa-solid fa-sliders"></i> {{ Translator::t('shell_section_configuration', 'Configuration') }}
                <i class="fa-solid fa-chevron-down" style="margin-left:auto;font-size:10px;transition:transform .15s" :style="configOpen ? 'transform:rotate(180deg)' : ''"></i>
            </button>
            <div x-show="configOpen" @click.stop>
                @foreach ($this->configMenuItems as $item)
                    @if ($standalone && $item->action_type === 'menu')
                        @if ($this->standalonePageUrl($item))
                            <button type="button" class="um-item" style="padding-left:28px" @click="ddOpen = false; pageTitle = @js($item->label); pageUrl = @js($this->standalonePageUrl($item))">
                                <i class="{{ $item->icon }}"></i> {{ $item->label }}
                            </button>
                        @endif
                    @else
                        <button type="button" class="um-item" style="padding-left:28px"
                            @click="ddOpen = false; {{ $item->action_type === 'menu' ? "openTab('".$item->action_target."')" : $item->action_target.' = true' }}">
                            <i class="{{ $item->icon }}"></i> {{ $item->label }}
                        </button>
                    @endif
                @endforeach
                <div class="um-item" style="cursor:default;padding-left:28px"><i class="fa-solid fa-globe"></i> {{ Translator::t('shell_language', 'Bahasa') }}
                    <span class="um-langs" style="margin-left:auto;display:flex;gap:6px">
                        @foreach ($this->availableLanguages as $lang)
                            <button type="button" class="um-flag {{ $lang['code'] === $this->currentLangCode() ? 'active' : '' }}"
                                title="{{ $lang['name'] }}"
                                @click.stop="$wire.setLang('{{ $lang['code'] }}').then(() => location.reload())">{{ $lang['flag'] ?? '🌐' }}</button>
                        @endforeach
                    </span>
                </div>
                <div class="um-cap" style="padding-left:28px">{{ Translator::t('shell_theme', 'Tema') }}</div>
                <div class="um-theme-matrix" @click.stop>
                    <div class="utm-hdr">
                        <span></span>
                        @foreach ($this->themeMatrix['hues'] as $h)<span>{{ $h }}</span>@endforeach
                    </div>
                    @foreach ($this->themeMatrix['matrix'] as $row)
                        <div class="utm-row">
                            <span class="utm-lbl">{{ $row['label'] }}</span>
                            @foreach ($row['cells'] as $cell)
                                @if ($cell)
                                    <button type="button" class="um-swatch {{ $cell['code'] === $this->currentThemeCode() ? 'active' : '' }}"
                                        title="{{ $cell['name'] }}" style="background:{{ $cell['primary'] }}"
                                        @click="$wire.setTheme('{{ $cell['code'] }}').then(() => location.reload())"></button>
                                @else
                                    <span class="utm-empty"></span>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </div>
                @if (! empty($this->themeMatrix['classic']))
                    <div class="um-cap" style="padding-left:28px">{{ Translator::t('shell_classic', 'Klasik') }}</div>
                    <div class="um-theme-grid" @click.stop>
                        @foreach ($this->themeMatrix['classic'] as $t)
                            <button type="button" class="um-swatch {{ $t['code'] === $this->currentThemeCode() ? 'active' : '' }}"
                                title="{{ $t['name'] }} ({{ $t['mode'] === 'dark' ? Translator::t('shell_theme_mode_dark', 'gelap') : Translator::t('shell_theme_mode_light', 'terang') }})"
                                style="background:{{ $t['primary'] }}"
                                @click="$wire.setTheme('{{ $t['code'] }}').then(() => location.reload())">
                                @if ($t['mode'] === 'dark')<i class="fa-solid fa-moon"></i>@endif
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="um-sep"></div>
            <button type="button" class="um-item danger" wire:click="logout"><i class="fa-solid fa-right-from-bracket"></i> {{ Translator::t('shell_logout', 'Keluar aplikasi') }}</button>
        </div>
    </div>
</div>
