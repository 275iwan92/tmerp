{{-- Setting E-mail sistem (tm2sys.sys_mail_setting) — admin. SMTP diisi di sini, bukan hanya .env (backlog09 §18). --}}
@extends('v2.layout', ['title' => 'Setting E-mail', 'bodyClass' => 'dl-page-body'])
@section('body')
    @php
        use App\Support\Mail\MailSettings;
        use App\Support\V2\Src;

        $s = MailSettings::current();
        $t = fn ($c) => Src::of('tm2sys.sys_mail_setting.'.$c);
    @endphp
    <div class="dl-cat">
        @if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
        @if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif
        <x-dl::action-form action="{{ route('v2.settings.mail.save') }}" autocomplete="off" data-dl-guard>
            <x-dl::card title="Setting E-mail (SMTP)" icon="email" description="Used by the Email button on every document and the user e-mail verification link. .env mode = use the server settings (MAIL_* in .env).">
                <x-dl::form.row cols="3">
                    <x-dl::field.select name="mailer" label="Send mode" required :options="['env' => 'Ikut .env server', 'smtp' => 'SMTP (isi di bawah)', 'log' => 'Log saja (tidak terkirim, untuk uji)']" :value="old('mailer', $s->mailer ?? 'env')" :source="$t('mailer')" />
                    <x-dl::field.text name="host" label="SMTP host" :case-toggle="false" :value="old('host', $s->host)" placeholder="smtp.domain.com" :source="$t('host')" />
                    <x-dl::field.number name="port" label="Port" :value="old('port', $s->port)" placeholder="587" :source="$t('port')" />
                </x-dl::form.row>
                <x-dl::form.row cols="3">
                    <x-dl::field.select name="encryption" label="Enkripsi" :options="['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', '' => 'Tanpa']" :value="old('encryption', $s->encryption ?? 'tls')" :source="$t('encryption')" />
                    <x-dl::field.text name="username" label="Username" :case-toggle="false" autocomplete="off" :value="old('username', $s->username)" :source="$t('username')" />
                    <x-dl::field.password name="password" label="Password" :hint="$s->password ? 'Tersimpan (terenkripsi). Kosongkan bila tidak diganti.' : 'Belum diisi'" :source="$t('password').' (terenkripsi)'" />
                </x-dl::form.row>
                <x-dl::form.row cols="2">
                    <x-dl::field.text name="from_address" label="Alamat pengirim sistem (From)" :case-toggle="false" :value="old('from_address', $s->from_address)" placeholder="noreply@domain.com" :source="$t('from_address')" />
                    <x-dl::field.text name="from_name" label="System sender name" :value="old('from_name', $s->from_name)" :source="$t('from_name')" />
                </x-dl::form.row>
                <x-dl::form.row cols="2">
                    <x-dl::field.checkbox name="from_user" text="From = the sender user's e-mail (only when the user e-mail is verified & SMTP allows it)" :value="(bool) old('from_user', $s->from_user)" :source="$t('from_user')" />
                    <x-dl::field.checkbox name="require_verified" text="Require a verified user e-mail before documents can be sent" :value="(bool) old('require_verified', $s->require_verified)" :source="$t('require_verified')" />
                </x-dl::form.row>
                <x-dl::alert tone="info" message="Bounces that arrive later are not read automatically — that needs access to the sender's inbox. Messages rejected immediately by the server are recorded as FAILED and the sender is notified." />
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="route('v2.home')" />
                    <x-dl::button variant="secondary" icon="send" label="Save & Send Test Email" type="submit" name="action" value="test" />
                    <x-dl::button preset="save" name="action" value="save" />
                </x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    </div>
@endsection
