<div class="dl-login dl-login--{{ $model }}" {!! $dl() !!} data-error="{{ $error }}">
    @if ($hasHero())
        <x-dl::hero :images="$heroImages">
            <x-dl::brand :name="$brand" :tagline="$tagline" class="dl-login__brand" />
            <x-dl::clock class="dl-login__clock" />
            <x-dl::quote :quotes="$quotes" class="dl-login__quote" />
        </x-dl::hero>
        @if ($model !== 'fullbg')<div class="dl-login__grip" title="Tarik untuk melebarkan"></div>@endif
    @endif

    <div class="dl-login__panel">
        <x-dl::lang-switch />
        <form class="dl-login__card" method="POST" action="{{ $action }}" novalidate>
            @csrf
            @if (! $hasHero())<x-dl::brand :name="$brand" :tagline="$tagline" tone="dark" />@endif
            <h2 data-i18n="heading">Masuk</h2>
            <p class="dl-login__sub" data-i18n="sub">Gunakan akun terdaftar untuk mengakses aplikasi.</p>
            <x-dl::alert />
            <x-dl::field.text name="login" label="Username atau email" i18n="l_login" icon="fa-user" size="lg" :value="$username" :case-toggle="false" autocomplete="username" />
            <x-dl::field.password name="password" label="Password" i18n="l_pass" icon="fa-lock" size="lg" />
            <x-dl::button variant="link" :href="$forgotUrl" label="Lupa password?" i18n="forgot" class="dl-login__forgot" />
            <x-dl::button type="submit" size="lg" block label="Masuk" i18n="btn" />
            <x-dl::divider label="atau" i18n="or" />
            <p class="dl-login__reg"><span data-i18n="no_acc">Belum punya akun?</span> <x-dl::button variant="link" :href="$registerUrl" label="Daftar" i18n="reg" /></p>
        </form>
    </div>
</div>
