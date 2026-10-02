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
                    <h2 class="dl-loginbox__title" data-i18n="heading">{{ \Devlib\Lang::text('Sign in') }}</h2>
                    <p class="dl-loginbox__sub" data-i18n="sub">{{ \Devlib\Lang::text('Use your registered account to access the app.') }}</p>
                    <x-dl::alert />
                    @break
                @case('username') <x-dl::field.text name="login" label="Username or e-mail" i18n="l_login" icon="fa-user" size="lg" :value="$username" :case-toggle="false" autocomplete="username" /> @break
                @case('email') <x-dl::field.text name="email" type="email" label="Email" i18n="l_email" icon="fa-envelope" size="lg" :case-toggle="false" autocomplete="email" /> @break
                @case('password') <x-dl::field.password name="password" label="Password" i18n="l_pass" icon="fa-lock" size="lg" /> @break
                @case('remember') <x-dl::field.checkbox name="remember" text="Remember me" class="dl-loginbox__remember" /> @break
                @case('forgot') <x-dl::button variant="link" :href="$forgotUrl" label="Forgot password?" i18n="forgot" class="dl-loginbox__forgot" /> @break
                @case('button') <x-dl::button type="submit" size="lg" block :label="$button" i18n="btn" /> @break
                @case('register')
                    <x-dl::divider label="or" :i18n="\Devlib\Lang::text('or')" />
                    <p class="dl-loginbox__reg"><span data-i18n="no_acc">{{ \Devlib\Lang::text('Don\'t have an account?') }}</span> <x-dl::button variant="link" :href="$registerUrl" label="Register" i18n="reg" /></p>
                    @break
            @endswitch
        @endforeach
    </form>
</div>
