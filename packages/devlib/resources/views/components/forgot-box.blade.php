<div class="dl-loginbox dl-auth" {!! $dl() !!} data-dl-forgot data-mode="{{ $mode }}">
    <div class="dl-loginbox__top"><x-dl::lang-switch /></div>
    <form class="dl-loginbox__form" method="POST" @if ($action) action="{{ $action }}" @endif novalidate data-fg-form>
        @csrf
        <h2 class="dl-loginbox__title" data-i18n="f_heading">{{ \Devlib\Lang::text('Forgot password') }}</h2>
        @if ($mode === 'email')
            <div data-fg-step="1">
                <p class="dl-loginbox__sub" data-i18n="f_sub_email">{{ \Devlib\Lang::text('Enter your account email. We will send a reset link.') }}</p>
                <x-dl::field.text name="email" type="email" label="Email" i18n="l_email" icon="fa-envelope" size="lg" placeholder="name@telasih.co.id" :case-toggle="false" autocomplete="email" required />
                <x-dl::button type="submit" size="lg" block :label="\Devlib\Lang::text('Send reset link')" i18n="f_send" />
            </div>
            <div class="dl-auth__ok" data-fg-step="done" hidden><i class="fa-solid fa-circle-check"></i> <span data-i18n="f_done_email">{{ \Devlib\Lang::text('If the email is registered, a reset link has been sent.') }}</span></div>
        @else
            <div class="dl-auth__steps"><span class="is-active" data-fg-dot="1">1 · <span data-i18n="f_s1">{{ \Devlib\Lang::text('Verify') }}</span></span><span data-fg-dot="2">2 · <span data-i18n="f_s2">{{ \Devlib\Lang::text('New password') }}</span></span></div>
            <x-dl::alert />
            <div data-fg-step="1">
                <p class="dl-loginbox__sub" data-i18n="f_sub_q">{{ \Devlib\Lang::text('Answer 4 questions matching your account. No email needed.') }}</p>
                <x-dl::field.text name="login" label="Username" i18n="l_login" icon="fa-user" :case-toggle="false" autocomplete="username" required />
                <div class="dl-auth__two">
                    <x-dl::field.date name="birth_date" label="Date of birth" i18n="f_birth" required />
                    <x-dl::field.phone name="phone" label="Registered phone" i18n="f_phone" kind="mobile" required />
                </div>
                <x-dl::field.text name="mother_name" label="Mother's maiden name" i18n="f_mother" icon="fa-person-dress" required />
                <x-dl::field.text name="security_answer" :label="$question" icon="fa-shield-halved" required />
                <x-dl::button type="submit" size="lg" block :label="\Devlib\Lang::text('Continue')" i18n="f_next" />
            </div>
            <div data-fg-step="2" hidden>
                <p class="dl-loginbox__sub" data-i18n="f_sub_new">{{ \Devlib\Lang::text('Data matched. Create a new password.') }}</p>
                <x-dl::field.password name="new_password" label="New password" i18n="f_new" icon="fa-lock" autocomplete="new-password" required hint="Min. 8 characters, a mix of letters & numbers." />
                <x-dl::field.password name="new_password_confirmation" label="Confirm" i18n="r_conf" icon="fa-lock" autocomplete="new-password" required />
                <x-dl::button type="submit" size="lg" block label="Save" i18n="f_save" />
            </div>
            <div class="dl-auth__done" data-fg-step="done" hidden>
                <span class="dl-auth__circle"><i class="fa-solid fa-check"></i></span>
                <h3 data-i18n="f_done_h">{{ \Devlib\Lang::text('Password updated') }}</h3>
                <p data-i18n="f_done_b">{{ \Devlib\Lang::text('Please sign in with your new password.') }}</p>
            </div>
        @endif
        <x-dl::button variant="link" icon="back" label="Back" :href="$loginUrl" class="dl-auth__back" />
    </form>
</div>
