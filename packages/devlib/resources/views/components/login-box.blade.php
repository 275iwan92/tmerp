<div class="dl-loginbox" {!! $dl() !!} data-dl-loginbox data-error="{{ $error }}">
    @if (array_intersect(['lang', 'support'], $parts()))
        <div class="dl-loginbox__top">
            @if (in_array('support', $parts(), true))<x-dl::uat-accounts />@endif
            @if (in_array('lang', $parts(), true))<x-dl::lang-switch />@endif
        </div>
    @endif
    <form class="dl-loginbox__form" method="POST" action="{{ $action }}" novalidate>
        @csrf
        @foreach ($parts() as $part)
            @switch($part)
                @case('brand') <x-dl::brand :name="$brand" :tagline="$tagline" tone="auto" class="dl-loginbox__brand" /> @break
                @case('title')
                    <h2 class="dl-loginbox__title" data-i18n="heading">Masuk</h2>
                    <p class="dl-loginbox__sub" data-i18n="sub">Gunakan akun terdaftar untuk mengakses aplikasi.</p>
                    <x-dl::alert />
                    @break
                @case('username') <x-dl::field.text name="login" label="Username or e-mail" i18n="l_login" icon="fa-user" size="lg" :value="$username" :case-toggle="false" autocomplete="username" /> @break
                @case('email') <x-dl::field.text name="email" type="email" label="Email" i18n="l_email" icon="fa-envelope" size="lg" :case-toggle="false" autocomplete="email" /> @break
                @case('password') <x-dl::field.password name="password" label="Password" i18n="l_pass" icon="fa-lock" size="lg" /> @break
                @case('remember') <x-dl::field.checkbox name="remember" text="Ingat saya" class="dl-loginbox__remember" /> @break
                @case('forgot') <x-dl::button variant="link" :href="$forgotUrl" label="Lupa password?" i18n="forgot" class="dl-loginbox__forgot" /> @break
                @case('button') <x-dl::button type="submit" size="lg" block :label="$button" i18n="btn" /> @break
                @case('register')
                    <x-dl::divider label="or" :i18n="\Devlib\Lang::text('or')" />
                    <p class="dl-loginbox__reg"><span data-i18n="no_acc">Belum punya akun?</span> <x-dl::button variant="link" :href="$registerUrl" label="Daftar" i18n="reg" /></p>
                    @break
            @endswitch
        @endforeach
    </form>
</div>
