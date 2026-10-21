{{--
 REMARK
 Tujuan       : Detail satu lowongan terbit: kode, judul, uraian, dan tombol Lamar (sudah masuk) atau Daftar / Masuk (belum).
 Sumber hulu  : CareersController::vacancy, CareersPortalService::publishedVacancy (tm2hrr.vac_vacancy).
 Input        : vacancy (VacVacancy), signedIn (sesi kandidat ada).
 Output       : x-mp::careers.vacancy-detail dengan tombol aksi.
 Dipakai oleh : Route careers.vacancy-detail.
 Status       : Aktif (T10 tahap 6b, 2026-10-21). Menggantikan pages::careers.vacancy-detail (Livewire v1).
--}}
@extends('careers.layout', ['title' => 'Detail Lowongan'])
@section('content')
    <x-mp::careers.page size="mid">
        <x-dl::button variant="link" icon="back" :label="raw('Kembali ke daftar lowongan')" :href="route('careers.landing')" />
        <x-mp::careers.vacancy-detail :code="$vacancy->vacancy_code" :title="$vacancy->title" :text="(string) $vacancy->description">
            @if ($signedIn)
                <x-dl::button variant="success" :label="raw('Lamar Posisi Ini')" :href="route('careers.apply', $vacancy->vacancy_code)" />
            @else
                <x-dl::button :label="raw('Daftar untuk Melamar')" :href="route('careers.register')" />
                <x-dl::hint as="span">Sudah punya akun? <x-dl::button variant="link" :label="raw('Masuk')" :href="route('careers.login')" /></x-dl::hint>
            @endif
        </x-mp::careers.vacancy-detail>
    </x-mp::careers.page>
@endsection
