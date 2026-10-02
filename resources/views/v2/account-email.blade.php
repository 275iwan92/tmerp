{{-- E-mail saya: status verifikasi sec_user.email + kirim link verifikasi. Alamatnya diubah admin di Security. --}}
@extends('v2.layout', ['title' => t('My E-mail'), 'bodyClass' => 'dl-page-body'])
@section('body')
    @php
        use App\Support\Mail\MailSettings;
        use App\Support\V2\Src;

        $u = auth()->user();
        $verified = MailSettings::verified($u);
        $setting = MailSettings::current();
    @endphp
    <div class="dl-cat">
        @if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
        @if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif
        <x-dl::card title="My E-mail" icon="email" description="This address is used when you send documents (Print > Email) and receive customer replies.">
            <x-dl::form.row cols="3">
                <x-dl::field.display label="E-mail" :value="$u->email ?: t('Not filled in yet — ask the admin to fill it in under Security')" :source="Src::of('tm2sys.sec_user.email')" />
                <x-dl::field.display label="Status" :value="$verified ? t('Verified').' '.\Devlib\Locale::dt($u->email_verified_at) : t('Not verified yet')" :source="Src::of('tm2sys.sec_user.email_verified', 'email_verified_at')" />
                <x-dl::field.display label="Verification required to send documents" :value="$setting->require_verified ? t('Yes') : t('No (not required by the admin yet)')" :source="Src::of('tm2sys.sys_mail_setting.require_verified')" />
            </x-dl::form.row>
            @if ($u->email && ! $verified)
                <x-dl::action-form action="{{ route('v2.account.email.send') }}">
                    <x-dl::action-bar><x-dl::button icon="send" :label="t('Send verification link')" type="submit" /></x-dl::action-bar>
                </x-dl::action-form>
            @endif
            <x-dl::action-bar><x-dl::button variant="secondary" icon="back" label="Back" :href="route('v2.home')" /></x-dl::action-bar>
        </x-dl::card>
    </div>
@endsection
