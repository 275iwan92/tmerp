{{--
 REMARK
 Tujuan       : Lamaran Saya: sapaan kandidat, peringatan surel belum terverifikasi dengan tombol kirim ulang, dan riwayat lamaran (kode lowongan, waktu kirim, status).
 Sumber hulu  : CareersController::dashboard / resendVerification, CareersPortalService::applications (Firestore 'submissions', app_status ditulis balik AppApplicationObserver).
 Input        : candidate (array akun), applications (koleksi kode, sent, label, tone); flash 'resent'.
 Output       : Headline, x-dl::alert, x-dl::card berisi x-dl::status-card per lamaran.
 Dipakai oleh : Route careers.dashboard (middleware portal.candidate).
 Status       : Aktif (T10 tahap 6b, 2026-10-21). Menggantikan pages::careers.dashboard (Livewire v1).
--}}
@extends('careers.layout', ['title' => 'Lamaran Saya'])
@section('content')
    <x-mp::careers.page size="mid">
        <x-dl::headline>Halo, {{ $candidate['name'] ?? '' }}</x-dl::headline>
        <x-dl::hint>{{ ($candidate['email'] ?? '').' · '.($candidate['national_id'] ?? '') }}</x-dl::hint>

        @if (empty($candidate['email_verified_at'] ?? null))
            <x-dl::alert tone="warn">
                Email belum diverifikasi -- lamaran tidak bisa dikirim sampai diverifikasi.
                @if (session('resent'))
                    <x-dl::badge tone="success" :label="raw('Terkirim ✓')" />
                @else
                    <x-dl::action-form :action="route('careers.resend')"><x-dl::button type="submit" size="sm" variant="secondary" :label="raw('Kirim ulang')" /></x-dl::action-form>
                @endif
            </x-dl::alert>
        @endif

        <x-dl::card :title="raw('Riwayat Lamaran')">
            @forelse ($applications as $app)
                <x-dl::status-card :title="raw($app['vacancy_code'])" :status="$app['label']" :tone="$app['tone']" icon="file" :desc="'Dikirim '.$app['sent']" />
            @empty
                <x-dl::hint>Belum ada lamaran. <x-dl::button variant="link" :label="raw('Lihat lowongan terbuka →')" :href="route('careers.landing')" /></x-dl::hint>
            @endforelse
        </x-dl::card>
    </x-mp::careers.page>
@endsection
