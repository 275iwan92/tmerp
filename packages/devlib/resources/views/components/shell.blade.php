<div class="dl-shell" {!! $dl() !!} data-dl-modules="{{ json_encode($modules) }}" data-dl-user="{{ json_encode($user) }}" data-dl-developer="{{ $developer ? '1' : '0' }}" data-dl-open-tab="{{ $open }}" data-dl-fresh="{{ $fresh ? '1' : '0' }}" data-dl-help="{{ $helpUrl }}">
    <aside class="dl-side" data-dl="sidebar kontekstual" data-dl-class="bagian Shell" data-dl-src="{{ $menuSource }} (grup + item modul aktif)">
        <div class="dl-side__head">
            <span class="dl-logo-badge dl-logo-badge--sm">@if ($logo)<img src="{{ $logo }}" alt="{{ $brand }}">@endif</span>
            <span class="dl-side__logo"><b>{{ $brand }}</b><br><span>{{ $tagline }}</span></span>
            <button type="button" class="dl-side__collapse" data-dl-act="mini" title="{{ \Devlib\Lang::text('Collapse sidebar (mini)') }}">⊟</button>
        </div>
        <div class="dl-side__title" data-dl-mod-title></div>
        <div class="dl-side__nav" data-dl-side-nav></div>
    </aside>

    <div class="dl-main">
        <header class="dl-topbar" data-dl="top bar + mega menu modul" data-dl-class="bagian Shell" data-dl-src="{{ $menuSource }} (menu_type = module)">
            <button type="button" class="dl-tb-btn" data-dl-act="nosidebar" title="{{ \Devlib\Lang::text('Hide sidebar') }}">☰</button>
            <nav class="dl-mega" data-dl-mega></nav>
            <div class="dl-tb-spacer"></div>
            <button type="button" class="dl-tb-btn" data-dl-act="palette" title="{{ \Devlib\Lang::text('Search / command (Ctrl K)') }}"><i class="fa-solid fa-magnifying-glass"></i></button>
            <button type="button" class="dl-tb-btn" data-dl-act="notopbar" title="{{ \Devlib\Lang::text('Hide top bar') }}"><i class="fa-solid fa-chevron-up"></i></button>
            <div class="dl-pop">
                <button type="button" class="dl-avatar-btn" data-dl-act="usermenu" title="{{ $user['name'] }}">
                    <span class="dl-avatar"><svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5 0-9 2.5-9 6v1h18v-1c0-3.5-4-6-9-6z"/></svg><span class="dl-av-dot" data-dl-avdot></span></span>
                    <i class="fa-solid fa-chevron-down dl-avatar-btn__caret"></i>
                </button>
                <div class="dl-pop-menu" data-dl-usermenu data-dl="menu avatar" data-dl-class="bagian Shell" data-dl-src="sys_user_menu (rencana)">
                    <div class="dl-um-head">
                        <span class="dl-um-av"><svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5 0-9 2.5-9 6v1h18v-1c0-3.5-4-6-9-6z"/></svg></span>
                        <div><div class="dl-um-name">{{ $user['name'] }}</div><div class="dl-um-mail">{{ $user['email'] }}</div><span class="dl-um-role">{{ $user['role'] }}</span></div>
                    </div>
                    <div class="dl-um-list">
                        <div class="dl-um-cap">{{ \Devlib\Lang::text('Messages & info') }}</div>
                        <button type="button" class="dl-um-item" data-dl-um="chat"><i class="fa-solid fa-comment-dots"></i> Chat<span class="dl-um-count" data-dl-count="chat">2</span></button>
                        <button type="button" class="dl-um-item" data-dl-um="notif"><i class="fa-solid fa-bell"></i> {{ \Devlib\Lang::text('Notifications') }}<span class="dl-um-count" data-dl-count="notif">3</span></button>
                        <button type="button" class="dl-um-item" data-dl-um="ai"><i class="fa-solid fa-wand-magic-sparkles"></i> {{ \Devlib\Lang::text('AI Assistant') }}</button>
                        <div class="dl-um-sep"></div>
                        <div class="dl-um-cap">{{ \Devlib\Lang::text('Account') }}</div>
                        <button type="button" class="dl-um-item" data-dl-um="edit"><i class="fa-solid fa-user-pen"></i> {{ \Devlib\Lang::text('Edit profile') }}</button>
                        <button type="button" class="dl-um-item" data-dl-um="pass"><i class="fa-solid fa-key"></i> {{ \Devlib\Lang::text('Change password') }}</button>
                        <button type="button" class="dl-um-item" data-dl-um="menu-layer"><i class="fa-solid fa-layer-group"></i> {{ \Devlib\Lang::text('Layer Menu (new menu)') }}</button>
                        <button type="button" class="dl-um-item" data-dl-um="help"><i class="fa-solid fa-circle-question"></i> {{ \Devlib\Lang::text('Help & support') }}</button>
                        <div class="dl-um-item dl-um-item--static"><i class="fa-solid fa-globe"></i> {{ \Devlib\Lang::text('Language') }}<span class="dl-um-langs"><button type="button" class="dl-um-flag is-active" data-dl-lang="id">🇮🇩</button><button type="button" class="dl-um-flag" data-dl-lang="en">🇬🇧</button></span></div>
                        @if ($developer)
                            <div class="dl-um-sep"></div>
                            <div class="dl-um-cap">Developer</div>
                            <button type="button" class="dl-um-item" data-dl-um="devmode"><i class="fa-solid fa-bug"></i> {{ \Devlib\Lang::text('Developer Mode') }}<span class="dl-um-switch" data-dl-devswitch></span></button>
                        @endif
                        <div class="dl-um-sep"></div>
                        <form method="POST" action="{{ $logoutAction }}" data-dl-logout>
                            @csrf
                            <button type="submit" class="dl-um-item dl-um-item--danger"><i class="fa-solid fa-right-from-bracket"></i> {{ \Devlib\Lang::text('Sign out') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>
        <div class="dl-topbar-restore"><button type="button" data-dl-act="showtopbar">▾ {{ \Devlib\Lang::text('Show top bar') }}</button></div>
        @include('devlib::components.shell.work', ['intro' => 'Choose a module in the top <b>mega menu</b> — its submenu appears in the sidebar.', 'hint' => 'Ctrl+K quick search · <kbd>⊟</kbd> mini sidebar · <kbd>☰</kbd> hide sidebar · <kbd>⤒</kbd> hide top bar.'])
    </div>

    @include('devlib::components.shell.overlays', ['chatDrawer' => true])
</div>
