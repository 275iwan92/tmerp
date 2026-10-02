<div class="dl-shell dl-shell--layer" {!! $dl() !!} data-dl-modules="{{ json_encode($modules) }}" data-dl-user="{{ json_encode($user) }}" data-dl-developer="{{ $developer ? '1' : '0' }}" data-dl-open-tab="{{ $open }}" data-dl-help="{{ $helpUrl }}" data-dl-profile-page="{{ $profilePage }}">
    <x-dl::layer-menu class="dl-shell__lm" id="shell" :items="$views() ? [] : $layerTree()" :views="$views()" :initial-view="$startView()" :title="$brand" :tagline="$tagline" :logo="$logo" search home :user="$user['name']" :role="$user['role'] ?? null" :login="$user['email'] ?? null" dock="[data-dl-lm-dock]" :actions="$actions()">
        <x-slot:chat><x-dl::chat compact :me="$chat['me'] ?? $user" :people="$chat['people'] ?? []" :convos="$chat['convos'] ?? []" :api="$chat['api'] ?? []" /></x-slot:chat>
        <x-slot:profile>
            <section data-lm-page="theme" data-label="Tema" data-icon="fa-palette"><x-dl::theme-picker /></section>
            <section data-lm-page="lang" data-label="Bahasa" data-icon="fa-globe"><x-dl::lang-switch /></section>
            @if (! empty($user['email_url']))
                <section data-lm-page="email" data-label="E-mail" data-icon="fa-envelope">
                    <p>{{ $user['email'] ?: 'Belum ada e-mail' }}</p>
                    <x-dl::badge :label="! empty($user['email_verified']) ? 'Terverifikasi' : 'Belum terverifikasi'" :tone="! empty($user['email_verified']) ? 'success' : 'warn'" />
                    <x-dl::action-bar><x-dl::button size="sm" icon="email" label="Kelola e-mail" :href="$user['email_url']" /></x-dl::action-bar>
                </section>
            @endif
            {{ $profilePages ?? '' }}
        </x-slot:profile>
    </x-dl::layer-menu>

    <div class="dl-main">
        @include('devlib::components.shell.work', ['intro' => 'Choose a module in the <b>left menu</b>; click a branch to go down one layer, <b>Back</b> to go up.', 'hint' => 'Ctrl+K quick search · the gear next to the name = Chat, Profile, Notifications, Classic Menu.'])
    </div>

    <aside class="dl-lm-dock dl-shell__dock" data-dl-lm-dock></aside>

    <form method="POST" action="{{ $logoutAction }}" data-dl-logout hidden>@csrf</form>
    @include('devlib::components.shell.overlays', ['chatDrawer' => false])
</div>
