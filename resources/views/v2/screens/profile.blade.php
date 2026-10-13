{{--
 REMARK
 Tujuan       : Edit profile layar penuh: data pribadi, cuti, dokumen, shift dan keamanan akun; perubahan data pribadi = pengajuan dengan approval HR, akun login dan pertanyaan pemulihan diubah langsung.
 Sumber hulu  : App\Support\V2\Data\SelfService::desk (port layar self-service v1 pages/self-service/index); data user dari Auth::user.
 Input        : User login (Auth::user), variabel crumbs dari layout.
 Output       : Objek form khusus x-mp::hrm.self-service; layar hanya satu baris deklarasi.
 Dipakai oleh : Rute v2.profile (menu Profile > Edit profile)
 Status       : Aktif
--}}
@php
    use App\Support\V2\Data\SelfService;
    use Illuminate\Support\Facades\Auth;
@endphp

{{-- Layar = objek form khusus x-mp::hrm.self-service (MockupParts); isi = SelfService::desk(). --}}
<x-mp::hrm.self-service :me="SelfService::desk(Auth::user(), route('v2.profile'))" :crumbs="$crumbs" />
