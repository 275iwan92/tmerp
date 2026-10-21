<?php

namespace App\Services\Portal;

use App\Exceptions\Portal\CandidateAlreadyExistsException;
use App\Mail\Portal\CandidateEmailVerification;
use App\Models\Hrr\VacVacancy;
use App\Models\Master\GeoCity;
use App\Services\Recruitment\FirebaseSyncAdapter;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/*
 * REMARK
 * Tujuan       : Aturan portal karir publik di satu tempat: daftar lowongan, sesi kandidat, registrasi (cek struktur NIK, surel verifikasi, masuk otomatis), masuk, keluar, verifikasi surel, riwayat lamaran dengan status. Dipindah dari halaman Livewire v1 tanpa mengubah perilaku.
 * Sumber hulu  : tm2hrr.vac_vacancy (lowongan terbit), Firestore 'candidates' lewat CandidateAuthService, Firestore 'submissions' lewat FirebaseSyncAdapter (app_status ditulis balik oleh AppApplicationObserver), tm2mst.geo_city (kode wilayah NIK).
 * Input        : kode lowongan, data registrasi, email + password, uid kandidat di sesi 'portal_candidate_uid'.
 * Output       : koleksi lowongan, array kandidat, daftar lamaran (kode, waktu kirim, label status, nada), surel verifikasi bertanda tangan 60 menit.
 * Dipakai oleh : App\Http\Controllers\Portal\CareersController, App\Services\Portal\CandidateApplicationService.
 * Status       : Aktif (T10 tahap 6b, 2026-10-21). Menggantikan logika di resources/views/pages/careers/{landing,vacancy-detail,register,login,dashboard}.blade.php.
 */
class CareersPortalService
{
    public const SESSION_KEY = 'portal_candidate_uid';

    public function __construct(protected CandidateAuthService $auth, protected FirebaseSyncAdapter $submissions) {}

    public function openVacancies(): Collection
    {
        return VacVacancy::where('is_published', true)->whereNull('closed_at')->orderByDesc('id_vacancy')->get();
    }

    public function publishedVacancy(string $code): VacVacancy
    {
        return VacVacancy::where('vacancy_code', $code)->where('is_published', true)->firstOrFail();
    }

    public function uid(): ?string
    {
        return session(self::SESSION_KEY);
    }

    public function candidate(): ?array
    {
        return $this->uid() ? $this->auth->findByUid($this->uid()) : null;
    }

    public function emailVerified(): bool
    {
        return ! empty($this->candidate()['email_verified_at'] ?? null);
    }

    public function signIn(array $candidate): void
    {
        session([self::SESSION_KEY => $candidate['_docId']]);
        session()->regenerate();
    }

    public function signOut(): void
    {
        session()->forget(self::SESSION_KEY);
        session()->regenerate();
    }

    public function attempt(string $email, string $password): ?array
    {
        return $this->auth->attempt($email, $password);
    }

    public function markEmailVerified(string $uid): void
    {
        $this->auth->markEmailVerified($uid);
    }

    /** @throws CandidateAlreadyExistsException */
    public function register(array $data): array
    {
        $candidate = $this->auth->register($data);
        $this->sendVerification($candidate);
        $this->signIn($candidate);

        return $candidate;
    }

    public function sendVerification(array $candidate): void
    {
        $url = URL::temporarySignedRoute('careers.verify-email', now()->addMinutes(60), ['uid' => $candidate['_docId']]);
        Mail::to($candidate['email'])->send(new CandidateEmailVerification($candidate['name'], $url));
    }

    // NIK tidak punya check-digit, tapi punya struktur baku (Dukcapil): 4 digit awal = kode wilayah, 6 digit berikut = tanggal lahir DDMMYY (perempuan: DD+40).
    public function nikRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! GeoCity::where('city_code', 'bps:'.substr($value, 0, 4))->exists()) {
                $fail('Kode wilayah pada No. KTP (4 digit awal) tidak dikenali.');

                return;
            }

            $day = (int) substr($value, 6, 2);
            $month = (int) substr($value, 8, 2);
            if ($day > 40) {
                $day -= 40;
            }
            if ($month < 1 || $month > 12 || ! checkdate($month, $day, 2000)) {
                $fail('Tanggal lahir pada No. KTP tidak valid.');
            }
        };
    }

    /** @return Collection<int, array{vacancy_code:string, sent:string, label:string, tone:string}> */
    public function applications(string $uid): Collection
    {
        return collect($this->submissions->findAllByCandidateUid($uid))->map(function (array $s) {
            $status = $s['app_status'] ?? (($s['sync_status'] ?? null) === 'synced' ? 'DIPROSES' : 'MENUNGGU SINKRONISASI');

            return [
                'vacancy_code' => $s['vacancy_code'] ?? '-',
                'sent' => isset($s['submitted_at']) ? Carbon::parse($s['submitted_at'])->translatedFormat('d M Y H:i') : '-',
                'label' => in_array($status, ['APPLIED', 'IN_PROGRESS'], true) ? 'DIPROSES' : $status,
                'tone' => match ($status) {
                    'HIRED' => 'success',
                    'REJECTED', 'WITHDRAWN' => 'danger',
                    'OFFERING' => 'violet',
                    'MENUNGGU SINKRONISASI' => 'neutral',
                    default => 'warn',
                },
            ];
        })->values();
    }
}
