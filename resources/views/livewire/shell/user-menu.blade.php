@php use App\Support\Localization\Translator; @endphp
{{--
    Dropdown avatar/user -- SATU implementasi dipakai main-menu.blade.php
    (Menu Operasional, $standalone=false, andalkan ddOpen/
    openTabs/activeTabCode/openTab() dari x-data root .shell yg SUDAH ADA)
    dan layouts/report.blade.php (Laporan Manajemen, $standalone=true,
    dibungkus x-data lokal sendiri di sana krn tidak ada tab MDI/panel).

    Panel drawer/modal di KEDUA shell dirender App\Livewire\Shell\UserPanels.
    $standalone (Laporan) hanya beda utk item yg di Operasional membuka tab/
    halaman penuh (Aktivitas saya, Pengaturan, My Security): dibuka di modal
    halaman (pageUrl), user tetap di mode Laporan.

    Tema & Bahasa dulu widget di dalam dropdown (grup "Configuration" yang
    dilipat); sejak 2026-09-23 pindah ke jendela Configuration (UM_CONFIG ->
    configWinOpen, App\Livewire\Shell\UserConfiguration) atas permintaan user.
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

            <button type="button" class="um-item danger" wire:click="logout"><i class="fa-solid fa-right-from-bracket"></i> {{ Translator::t('shell_logout', 'Keluar aplikasi') }}</button>
        </div>
    </div>
</div>
