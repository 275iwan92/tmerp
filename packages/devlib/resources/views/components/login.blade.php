<div class="dl-login dl-login--{{ $model }}" {!! $dl() !!}
    data-quotes="{{ json_encode($quotes) }}" data-heroes="{{ json_encode($heroImages) }}" data-error="{{ $error }}">
    @if ($hasHero())
        <div class="dl-login__hero" data-dl="hero · foto + jam + kutipan" data-dl-class="bagian Login" data-dl-src="sys_login_hero · sys_quotes">
            <img class="dl-login__img" alt="">
            <div class="dl-login__veil"></div>
            <div class="dl-login__brand"><span class="dl-logo-badge"></span><div><b>{{ $brand }}</b><span>{{ $tagline }}</span></div></div>
            <div class="dl-login__clock"><div class="t" data-dl-clock-time>--:--:--</div><div class="d" data-dl-clock-date>—</div></div>
            <div class="dl-login__quote"><div class="qi">&#8220;</div><div class="q" data-dl-quote>—</div><div class="a" data-dl-author>—</div></div>
        </div>
        @if ($model !== 'fullbg')
            <div class="dl-login__grip" title="Tarik untuk melebarkan" data-dl="grip pelebar panel" data-dl-class="bagian Login" data-dl-src="—"></div>
        @endif
    @endif

    <div class="dl-login__panel">
        <div class="dl-login__langbar" data-dl="pilih bahasa" data-dl-class="bagian Login" data-dl-src="sec_user.lang_code (rencana)">
            <button type="button" class="dl-flag is-active" data-lang="id" title="Bahasa Indonesia">🇮🇩</button>
            <button type="button" class="dl-flag" data-lang="en" title="English">🇬🇧</button>
        </div>
        <div class="dl-login__formwrap">
            <form class="dl-login__card" method="POST" action="{{ $action }}" novalidate>
                @csrf
                @if ($model === 'minimal' || $model === 'center')
                    <div class="dl-login__cardbrand"><span class="dl-logo-badge"></span><div><b>{{ $brand }}</b><span>{{ $tagline }}</span></div></div>
                @endif
                <h2 data-i18n="heading">Masuk</h2>
                <p class="dl-login__sub" data-i18n="sub">Gunakan akun terdaftar untuk mengakses aplikasi.</p>
                <div class="dl-login__alert" data-dl-alert></div>
                <div class="dl-login__field">
                    <label for="dl-user" data-i18n="l_login">Username atau email</label>
                    <div class="dl-ig" data-dl-wrap-user><i class="fa-solid fa-user"></i><input id="dl-user" name="login" value="{{ $username }}" autocomplete="username"></div>
                </div>
                <div class="dl-login__field">
                    <label for="dl-pass" data-i18n="l_pass">Password</label>
                    <div class="dl-ig" data-dl-wrap-pass><i class="fa-solid fa-lock"></i><input id="dl-pass" name="password" type="password" autocomplete="current-password"><button class="dl-ig__eye" type="button" data-dl-eye title="Tampilkan password"><i class="fa-solid fa-eye"></i></button></div>
                </div>
                <div class="dl-login__row"><a href="{{ $forgotUrl }}" data-i18n="forgot">Lupa password?</a></div>
                <button class="dl-login__btn" type="submit" data-i18n="btn">Masuk</button>
                <div class="dl-login__divider"><span data-i18n="or">atau</span></div>
                <div class="dl-login__reg"><span data-i18n="no_acc">Belum punya akun?</span> <a href="{{ $registerUrl }}" data-i18n="reg">Daftar</a></div>
            </form>
        </div>
    </div>
</div>
