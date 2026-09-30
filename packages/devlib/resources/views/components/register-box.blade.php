<div class="dl-loginbox dl-auth" {!! $dl() !!} data-dl-register>
    <div class="dl-loginbox__top"><x-dl::lang-switch /></div>
    <form class="dl-loginbox__form" method="POST" @if ($action) action="{{ $action }}" @endif novalidate data-rg-form>
        @csrf
        <div data-rg-box>
            <h2 class="dl-loginbox__title" data-i18n="r_heading">Daftar akun</h2>
            <p class="dl-loginbox__sub" data-i18n="r_sub">Buat akun baru untuk mengakses aplikasi.</p>
            <div class="dl-auth__note"><i class="fa-solid fa-circle-info"></i><span data-i18n="r_approval">Akun baru harus disetujui admin. Grup & hak akses ditentukan admin saat persetujuan.</span></div>
            <x-dl::alert />
            <x-dl::field.select name="reg_type" label="Daftar sebagai" i18n="r_type" icon="fa-user-tag" :options="$types" value="customer" required />
            <x-dl::field.text name="name" label="Full name" i18n="r_name" icon="fa-id-card" placeholder="Andi Pratama" required />
            <div class="dl-auth__with">
                <x-dl::field.text name="email" type="email" label="Email" i18n="r_email" icon="fa-envelope" placeholder="andi@perusahaan.com" :case-toggle="false" autocomplete="email" required hint="We check that the e-mail is valid and then send a verification link." />
                <x-dl::button variant="secondary" size="sm" label="Verifikasi" i18n="r_verify" data-rg-verify-email />
            </div>
            <x-dl::field.text name="username" label="Username (automatic from e-mail)" i18n="r_user" icon="fa-at" placeholder="filled from e-mail" readonly :case-toggle="false" />
            <div class="dl-auth__with">
                <x-dl::field.phone name="whatsapp" label="Active WhatsApp no." i18n="r_wa" kind="mobile" required hint="The number must have WhatsApp — the OTP code is sent there." />
                <x-dl::button variant="success" size="sm" icon="submit" :label="\Devlib\Lang::text('Send OTP')" i18n="r_send_otp" data-rg-send-otp />
            </div>
            <div class="dl-auth__with" data-rg-otp hidden>
                <x-dl::field.otp name="otp" label="OTP code from WhatsApp" i18n="r_otp" />
                <x-dl::button size="sm" label="Verifikasi" i18n="r_verify" data-rg-verify-otp />
            </div>
            <x-dl::field.text name="id_no" label="NIK (No. KTP)" icon="fa-address-card" placeholder="16 digit" :case-toggle="false" required data-rg-idno />
            <div class="dl-auth__two">
                <x-dl::field.password name="password" label="Password" i18n="r_pass" icon="fa-lock" autocomplete="new-password" required hint="Min. 8 karakter, kombinasi huruf & angka." />
                <x-dl::field.password name="password_confirmation" label="Konfirmasi" i18n="r_conf" icon="fa-lock" autocomplete="new-password" required />
            </div>
            <x-dl::button type="submit" size="lg" block :label="\Devlib\Lang::text('Submit registration')" i18n="r_submit" />
            <x-dl::button variant="link" icon="back" label="Back" :href="$loginUrl" class="dl-auth__back" />
        </div>
        <div class="dl-auth__done" data-rg-done hidden>
            <span class="dl-auth__circle"><i class="fa-solid fa-check"></i></span>
            <h3 data-i18n="r_done_h">Pendaftaran terkirim</h3>
            <p data-i18n="r_done_b">Akun Anda menunggu persetujuan admin. Setelah disetujui, Anda bisa masuk memakai email dan password ini.</p>
            <x-dl::button variant="link" icon="back" label="Back" :href="$loginUrl" class="dl-auth__back" />
        </div>
    </form>
</div>
