<div class="dl-shell dl-shell--layer" {!! $dl() !!} data-dl-modules="{{ json_encode($modules) }}" data-dl-user="{{ json_encode($user) }}" data-dl-developer="{{ $developer ? '1' : '0' }}" data-dl-open="{{ $open }}">
    <x-dl::layer-menu class="dl-shell__lm" id="shell" :items="$views() ? [] : $layerTree()" :views="$views()" :initial-view="$startView()" :title="$brand" :user="$user['name']" :role="$user['role'] ?? null" :login="$user['email'] ?? null" dock="[data-dl-lm-dock]" :actions="$actions()">
        <x-slot:chat><x-dl::chat compact :me="$chat['me'] ?? $user" :people="$chat['people'] ?? []" :convos="$chat['convos'] ?? []" /></x-slot:chat>
        <x-slot:profile>
            <section data-lm-page="theme" data-label="Tema" data-icon="fa-palette"><x-dl::theme-picker /></section>
            <section data-lm-page="lang" data-label="Bahasa" data-icon="fa-globe"><x-dl::lang-switch /></section>
        </x-slot:profile>
    </x-dl::layer-menu>

    <div class="dl-main">
        @include('devlib::components.shell.work', ['intro' => 'Pilih modul di <b>menu kiri</b>; klik cabang untuk turun satu layer, <b>Back</b> untuk naik.', 'hint' => 'Ctrl+K cari cepat · roda gigi di sebelah nama = Chat, Profile, Notifikasi, Menu Classic.'])
    </div>

    <aside class="dl-lm-dock dl-shell__dock" data-dl-lm-dock></aside>

    <form method="POST" action="{{ $logoutAction }}" data-dl-logout hidden>@csrf</form>
    @include('devlib::components.shell.overlays', ['chatDrawer' => false])
</div>
