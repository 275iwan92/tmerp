{{--
 REMARK
 Tujuan       : Kerangka semua halaman portal karir publik: v2.layout + x-mp::careers.shell (bilah atas, isi, kaki halaman).
 Sumber hulu  : sesi kandidat portal_candidate_uid, x-mp::careers.shell (merek TELASIH MAKMUR, sama seperti portal v1).
 Input        : title (judul tab); isi = section content dari halaman anak.
 Output       : Dokumen HTML lengkap lewat v2.layout.
 Dipakai oleh : careers.landing, vacancy, register, login, dashboard, apply.
 Status       : Aktif (T10 tahap 6b, 2026-10-21). Menggantikan resources/views/layouts/portal.blade.php.
--}}
@extends('v2.layout', ['title' => $title ?? 'Karir', 'bodyClass' => 'mp-careers-body'])
@section('body')
    <x-mp::careers.shell brand="TELASIH MAKMUR" :signed-in="(bool) session('portal_candidate_uid')"
        :urls="['home' => route('careers.landing'), 'dashboard' => route('careers.dashboard'), 'login' => route('careers.login'), 'register' => route('careers.register'), 'logout' => route('careers.logout')]"
        :footer="'© '.date('Y').' CV. Telasih Makmur — Percetakan Offset & Packaging'" footer-note="Pertanyaan seputar lamaran? Hubungi HRD di jam kerja.">
        @yield('content')
    </x-mp::careers.shell>
@endsection
