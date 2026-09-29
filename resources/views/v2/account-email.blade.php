{{-- E-mail saya: status verifikasi sec_user.email + kirim link verifikasi. Alamatnya diubah admin di Security. --}}
@extends('v2.layout', ['title' => 'E-mail Saya', 'bodyClass' => 'dl-page-body'])
@section('body')
    @php
        use App\Support\Mail\MailSettings;
        use App\Support\V2\Src;

        $u = auth()->user();
        $verified = MailSettings::verified($u);
        $setting = MailSettings::current();
    @endphp
    <div class="dl-cat">
        @if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
        @if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif
        <x-dl::card title="E-mail Saya" icon="email" description="Alamat ini dipakai saat Anda mengirim dokumen (Print > Email) dan menerima balasan customer.">
            <x-dl::form.row cols="3">
                <x-dl::field.display label="E-mail" :value="$u->email ?: 'Belum diisi — minta admin mengisinya di Security'" :source="Src::of('tm2sys.sec_user.email')" />
                <x-dl::field.display label="Status" :value="$verified ? 'Terverifikasi '.$u->email_verified_at?->format('d M Y H:i') : 'Belum terverifikasi'" :source="Src::of('tm2sys.sec_user.email_verified', 'email_verified_at')" />
                <x-dl::field.display label="Wajib verifikasi untuk kirim dokumen" :value="$setting->require_verified ? 'Ya' : 'Tidak (belum diwajibkan admin)'" :source="Src::of('tm2sys.sys_mail_setting.require_verified')" />
            </x-dl::form.row>
            @if ($u->email && ! $verified)
                <form method="post" action="{{ route('v2.account.email.send') }}">
                    @csrf
                    <x-dl::action-bar><x-dl::button icon="send" label="Kirim link verifikasi" type="submit" /></x-dl::action-bar>
                </form>
            @endif
            <x-dl::action-bar><x-dl::button variant="secondary" icon="back" label="Back" :href="route('v2.home')" /></x-dl::action-bar>
        </x-dl::card>
    </div>
@endsection
