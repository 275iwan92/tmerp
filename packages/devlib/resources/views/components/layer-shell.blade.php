<div class="dl-shell dl-shell--layer" {!! $dl() !!} data-dl-modules="{{ json_encode($modules) }}" data-dl-user="{{ json_encode($user) }}" data-dl-developer="{{ $developer ? '1' : '0' }}" data-dl-open-tab="{{ $open }}" data-dl-fresh="{{ $fresh ? '1' : '0' }}" data-dl-help="{{ $helpUrl }}" data-dl-profile-page="{{ $profilePage }}">
    <x-dl::layer-menu class="dl-shell__lm" id="shell" :items="$views() ? [] : $layerTree()" :views="$views()" :initial-view="$startView()" :title="\Devlib\Lang::raw($brand)" :tagline="$tagline" :logo="$logo" search home :user="$user['name']" :role="$user['role'] ?? null" :login="$user['email'] ?? null" dock="[data-dl-lm-dock]" :actions="$actions()">
        <x-slot:chat><x-dl::chat compact :me="$chat['me'] ?? $user" :people="$chat['people'] ?? []" :convos="$chat['convos'] ?? []" :api="$chat['api'] ?? []" /></x-slot:chat>
        <x-slot:profile>
            {{-- Edit profile = satu rumah untuk semua hal akun (user 2026-10-03): nama / e-mail / telepon, Change password,
                 E-mail, lalu tautan ke layar penuh (data diri, cuti, keamanan, ...) yang dibuka sebagai tab kerja. --}}
            @php $profileLive = ! empty($account['profile']['url']); $passLive = ! empty($account['password']['url']); @endphp
            <section data-lm-page="edit" data-label="{{ \Devlib\Lang::text('Edit profile') }}" data-icon="fa-user-pen" data-lm-children="pass,email">
                <div data-dl-account-box>
                    <div class="dl-modal__msg" data-dl-account-msg hidden></div>
                    <div class="dl-mfield"><label>{{ \Devlib\Lang::text('Full name') }}</label><input name="name" maxlength="255" value="{{ $user['name'] }}"></div>
                    <div class="dl-mfield"><label>Email</label><input name="email" type="email" maxlength="255" value="{{ $user['email'] }}"></div>
                    <div class="dl-mfield"><label>{{ \Devlib\Lang::text('Phone no.') }}</label><input name="phone" maxlength="64" value="{{ $profileLive ? ($account['profile']['phone'] ?? '') : '0812-3456-7890' }}"></div>
                    <div class="dl-mfield"><label>{{ \Devlib\Lang::text('Position') }}</label><input value="{{ $profileLive ? ($account['profile']['role'] ?? '') : $user['role'] }}" readonly></div>
                    <x-dl::action-bar><x-dl::button size="sm" icon="fa-floppy-disk" label="Save" @if ($profileLive) data-dl-account-save="profile" @endif /></x-dl::action-bar>
                </div>
                @if ($profileLinks)
                    <div class="dl-lm__sep"></div>
                    <div class="dl-lm__plist">
                        @foreach ($profileLinks as $l)
                            <button type="button" class="dl-lm__a" data-dl-tab-url="{{ $l['url'] }}" data-dl-tab-code="{{ $l['code'] ?? 'P-'.$loop->index }}" data-dl-tab-label="{{ $l['label'] }}" data-dl-tab-icon="fa-solid {{ $l['icon'] ?? 'fa-id-card' }}"><span class="dl-lm__ico"><i class="fa-solid {{ $l['icon'] ?? 'fa-id-card' }}"></i></span><span class="dl-lm__t">{{ $l['label'] }}</span><i class="fa-solid fa-arrow-up-right-from-square dl-lm__chev"></i></button>
                        @endforeach
                    </div>
                @endif
            </section>
            <section data-lm-page="pass" data-label="{{ \Devlib\Lang::text('Change password') }}" data-icon="fa-key">
                <div data-dl-account-box>
                    <div class="dl-modal__msg" data-dl-account-msg hidden></div>
                    <div class="dl-mfield"><label>{{ \Devlib\Lang::text('Old password') }}</label><input type="password" name="old_password" autocomplete="current-password"></div>
                    <div class="dl-mfield"><label>{{ \Devlib\Lang::text('New password') }}</label><input type="password" name="new_password" autocomplete="new-password"></div>
                    <div class="dl-mfield"><label>{{ \Devlib\Lang::text('Confirm new password') }}</label><input type="password" name="new_password_confirmation" autocomplete="new-password"></div>
                    <x-dl::action-bar><x-dl::button size="sm" icon="fa-floppy-disk" label="Save" @if ($passLive) data-dl-account-save="password" @endif /></x-dl::action-bar>
                </div>
            </section>
            @if (! empty($user['email_url']))
                <section data-lm-page="email" data-label="E-mail" data-icon="fa-envelope">
                    <p>{{ $user['email'] ?: \Devlib\Lang::text('No e-mail yet') }}</p>
                    <x-dl::badge :label="! empty($user['email_verified']) ? 'Verified' : 'Not verified'" :tone="! empty($user['email_verified']) ? 'success' : 'warn'" />
                    <x-dl::action-bar><x-dl::button size="sm" icon="email" label="Manage e-mail" :href="$user['email_url']" /></x-dl::action-bar>
                </section>
            @endif
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
    @include('devlib::components.shell.overlays', ['chatDrawer' => false])
</div>
