<div class="dl-shell dl-shell--layer" {!! $dl() !!} data-dl-modules="{{ json_encode($modules) }}" data-dl-user="{{ json_encode($user) }}" data-dl-developer="{{ $developer ? '1' : '0' }}" data-dl-open-tab="{{ $open }}" data-dl-fresh="{{ $fresh ? '1' : '0' }}" data-dl-help="{{ $helpUrl }}" data-dl-profile-page="{{ $profilePage }}">
    <x-dl::layer-menu class="dl-shell__lm" id="shell" :items="$views() ? [] : $layerTree()" :views="$views()" :initial-view="$startView()" :title="\Devlib\Lang::raw($brand)" :tagline="$tagline" :logo="$logo" search home :user="$user['name']" :role="$user['role'] ?? null" :login="$user['email'] ?? null" dock="[data-dl-lm-dock]" :actions="$actions()">
        <x-slot:profile>
            {{-- Setup = rumah pengaturan tampilan yang akan bertambah (tema, bahasa, ...); Theme & Language anaknya (user 2026-10-03). --}}
            <section data-lm-page="setup" data-label="{{ \Devlib\Lang::text('Setup') }}" data-icon="fa-sliders" data-lm-children="theme,lang"></section>
            <section data-lm-page="theme" data-label="{{ \Devlib\Lang::text('Theme') }}" data-icon="fa-palette"><x-dl::theme-picker /></section>
            <section data-lm-page="lang" data-label="{{ \Devlib\Lang::text('Language') }}" data-icon="fa-globe"><x-dl::lang-switch /></section>
            {{ $profilePages ?? '' }}
        </x-slot:profile>
    </x-dl::layer-menu>

    <div class="dl-main">
        @include('devlib::components.shell.work', ['intro' => 'Choose a module in the <b>left menu</b>; click a branch to go down one layer, <b>Back</b> to go up.', 'hint' => 'Ctrl+K quick search · the gear next to the name = Chat, Profile, Notifications, Classic Menu.'])
    </div>

    <aside class="dl-lm-dock dl-shell__dock" data-dl-lm-dock></aside>

    <form method="POST" action="{{ $logoutAction }}" data-dl-logout hidden>@csrf</form>
    {{-- Chat = laci kanan Shell, bukan panel kiri (user 2026-10-03). --}}
    @include('devlib::components.shell.overlays', ['chatDrawer' => true])
</div>
