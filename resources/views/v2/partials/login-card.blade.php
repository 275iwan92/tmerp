{{--
 REMARK
 Tujuan       : Dialog kartu login (login, nama, password awal) yang tampil SEKALI sesudah akun dibuat, di-reset atau dilihat ulang; satu sumber untuk semua layar yang menampilkan kredensial (objek DevLib x-dl::modal + x-dl::login-card).
 Sumber hulu  : Flash session v2_login_card = ['login', 'name', 'password', 'code' (kode pegawai, opsional), 'title' (opsional)].
 Input        : Tanpa parameter; membaca session('v2_login_card').
 Output       : x-dl::modal terbuka dengan kartu login dan tombol Close.
 Dipakai oleh : hrm-employee-form (akun pegawai baru / reset), security-account-form (reset dan lihat password).
 Status       : Aktif (T8 Setup; diekstrak dari hrm-employee-form supaya tidak ada dua salinan).
--}}
@if ($card = session('v2_login_card'))
    <x-dl::modal id="login-card-dialog" :title="$card['title'] ?? 'Login Card'" icon="fa-id-card" :open="true">
        <x-dl::login-card :title="$card['title'] ?? 'Login card'" :name="raw($card['name'])" :code="$card['code'] ?? ''" :login="raw($card['login'])" :password="raw($card['password'])">
            <x-dl::button type="button" variant="secondary" label="Close" data-dl-close-modal />
        </x-dl::login-card>
    </x-dl::modal>
@endif
