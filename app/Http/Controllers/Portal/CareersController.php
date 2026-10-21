<?php

namespace App\Http\Controllers\Portal;

use App\Exceptions\Portal\CandidateAlreadyExistsException;
use App\Http\Controllers\Controller;
use App\Services\Portal\CandidateApplicationService;
use App\Services\Portal\CareersPortalService;
use App\Support\Recruitment\CandidateFormOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/*
 * REMARK
 * Tujuan       : Halaman portal karir publik (kandidat eksternal, akun terpisah dari sec_user): lowongan, detail, daftar, masuk, keluar, verifikasi surel, riwayat lamaran, form lamar dan kirim. Tipis: aturan ada di CareersPortalService dan CandidateApplicationService.
 * Sumber hulu  : routes/web.php (grup hrm/recruitment/portal, nama careers.*), middleware portal.candidate untuk lamar dan riwayat.
 * Input        : request HTTP; sesi portal_candidate_uid.
 * Output       : view careers.* (objek x-mp::careers.* dan x-dl::*), redirect dengan pesan sesi.
 * Dipakai oleh : routes/web.php. Menggantikan halaman Livewire pages::careers.*.
 * Status       : Aktif (T10 tahap 6b, 2026-10-21).
 */
class CareersController extends Controller
{
    public function __construct(protected CareersPortalService $portal, protected CandidateApplicationService $application) {}

    public function landing(): View
    {
        return view('careers.landing', ['vacancies' => $this->portal->openVacancies()]);
    }

    public function vacancy(string $code): View
    {
        return view('careers.vacancy', ['vacancy' => $this->portal->publishedVacancy($code), 'signedIn' => (bool) $this->portal->uid()]);
    }

    public function registerForm(): View
    {
        return view('careers.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'national_id' => ['required', 'digits:16', $this->portal->nikRule()],
            'name' => ['required', 'min:3'],
            'email' => ['required', 'email', 'regex:/^[^@\s]+@[^@\s]+\.[^@\s]{2,}$/'],
            'phone' => ['required', 'min:9', 'max:15'],
            'password' => ['required', 'min:8', 'confirmed'],
        ], [
            'national_id.digits' => 'No. KTP harus 16 digit angka.',
            'email.regex' => 'Email harus punya domain lengkap, mis. nama@contoh.com.',
        ]);

        try {
            $this->portal->register($data);
        } catch (CandidateAlreadyExistsException $e) {
            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors(['email' => $e->getMessage()]);
        }

        return redirect()->route('careers.register')->with('registered', $data['email']);
    }

    public function loginForm(): View
    {
        session()->keep('intended_url');

        return view('careers.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);

        $candidate = $this->portal->attempt((string) $request->input('email'), (string) $request->input('password'));
        if (! $candidate) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'Email atau password salah.']);
        }

        $this->portal->signIn($candidate);

        return redirect(session()->pull('intended_url') ?? route('careers.dashboard'));
    }

    public function logout(): RedirectResponse
    {
        $this->portal->signOut();

        return redirect()->route('careers.landing');
    }

    public function verifyEmail(string $uid): RedirectResponse
    {
        if (! request()->hasValidSignature()) {
            abort(403, 'Tautan verifikasi tidak valid atau sudah kedaluwarsa.');
        }

        $this->portal->markEmailVerified($uid);

        return redirect()->route('careers.login')->with('verified', true);
    }

    public function dashboard(): View
    {
        $candidate = $this->portal->candidate();

        return view('careers.dashboard', ['candidate' => $candidate, 'applications' => $this->portal->applications($this->portal->uid())]);
    }

    public function resendVerification(): RedirectResponse
    {
        $this->portal->sendVerification($this->portal->candidate());

        return redirect()->route('careers.dashboard')->with('resent', true);
    }

    public function apply(string $code): View
    {
        $vacancy = $this->portal->publishedVacancy($code);
        $candidate = $this->portal->candidate();

        return view('careers.apply', [
            'vacancy' => $vacancy,
            'submitted' => session('submitted'),
            'verified' => $this->portal->emailVerified(),
            'f' => $this->application->formValues($candidate),
            'options' => CandidateFormOptions::class,
        ]);
    }

    public function submit(Request $request, string $code): RedirectResponse
    {
        $this->portal->publishedVacancy($code);
        $candidate = $this->portal->candidate();

        if (! $this->portal->emailVerified()) {
            return back()->withInput($request->except('cv_file', 'ijazah_file'))->withErrors(['apply' => 'Verifikasi email Anda dulu sebelum mengirim lamaran (cek inbox saat registrasi).']);
        }

        $validator = $this->application->validator($request->all());
        if ($validator->fails()) {
            return back()->withInput($request->except('cv_file', 'ijazah_file'))->withErrors($validator);
        }

        $uuid = $this->application->submit($request->all(), $candidate, $code, $request->file('cv_file'), $request->file('ijazah_file'));

        return redirect()->route('careers.apply', $code)->with('submitted', $uuid);
    }
}
