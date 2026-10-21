{{--
 REMARK
 Tujuan       : Halaman depan portal karir: pita judul dan daftar lowongan terbuka (terbit, belum ditutup) sebagai kartu tautan ke detail.
 Sumber hulu  : CareersController::landing, CareersPortalService::openVacancies (tm2hrr.vac_vacancy).
 Input        : vacancies (koleksi VacVacancy).
 Output       : Hero + x-mp::careers.vacancy-list berisi x-mp::careers.vacancy-card.
 Dipakai oleh : Route careers.landing (/hrm/recruitment/portal).
 Status       : Aktif (T10 tahap 6b, 2026-10-21). Menggantikan pages::careers.landing (Livewire v1).
--}}
@extends('careers.layout', ['title' => 'Karir'])
@section('content')
    <x-mp::careers.hero :title="raw('Bangun Karir Anda di Telasih Makmur')" :sub="raw('Perusahaan percetakan offset & packaging yang terus berkembang. Temukan posisi yang sesuai dengan keahlian Anda.')" />
    <x-mp::careers.page size="wide">
        <x-mp::careers.vacancy-list :title="raw('Lowongan Terbuka')" :count="$vacancies->count()" :empty="raw('Belum ada lowongan terbuka saat ini. Silakan cek kembali lain waktu.')">
            @foreach ($vacancies as $vac)
                <x-mp::careers.vacancy-card :code="$vac->vacancy_code" :title="$vac->title" :text="(string) $vac->description" :href="route('careers.vacancy-detail', $vac->vacancy_code)" :cta="raw('Lihat detail & lamar')" />
            @endforeach
        </x-mp::careers.vacancy-list>
    </x-mp::careers.page>
@endsection
