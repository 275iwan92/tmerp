<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\LoginService;
use App\Services\Security\SecurityRecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/*
 * REMARK
 * Tujuan       : Setup 4 pertanyaan keamanan (keputusan user 2026-08-27), dipaksa middleware force.password selama security_setup_at masih kosong, terlepas dari must_change_password. Tanggal lahir dan nomor HP terisi otomatis dari emp_employee bila akun tertaut karyawan.
 * Sumber hulu  : App\Services\Security\SecurityRecoveryService (resolve*FromEmployee, activeQuestions, saveSetup, syncIdentityToEmployee), sec_recovery_question.
 * Input        : GET /security/setup; POST (birth_date, mother_name, phone, question_id, answer); keluar lewat route logout.
 * Output       : View auth.security-setup; sukses = simpan jawaban + sinkron identitas ke karyawan, lalu redirect ke tujuan semula atau menu.
 * Dipakai oleh : routes/web.php (middleware auth, locale; di luar force.password supaya tidak berputar).
 * Status       : Aktif (T10 tahap 6a, 2026-10-21). Dipindahkan apa adanya dari halaman v1 pages::auth.security-setup.
 */
class SecuritySetupController extends Controller
{
    public function show(SecurityRecoveryService $service, LoginService $login): View
    {
        $user = Auth::user();
        $birth = $service->resolveBirthDateFromEmployee($user);
        $phone = $service->resolvePhoneFromEmployee($user);

        return view('auth.security-setup', [
            'images' => $login->heroImages(), 'quotes' => $login->quotes(), 'questions' => $service->activeQuestions(),
            'birthDate' => $birth, 'phone' => $phone ?: (string) $user->phone,
            'birthLocked' => (bool) $birth, 'phoneLocked' => (bool) $phone,
        ]);
    }

    public function store(Request $request, SecurityRecoveryService $service): RedirectResponse
    {
        $data = $request->validate([
            'birth_date' => ['required', 'date'],
            'mother_name' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'question_id' => ['required', 'integer', 'exists:tm2sys.sec_recovery_question,id_question'],
            'answer' => ['required', 'string', 'min:2', 'max:150'],
        ]);
        $user = Auth::user();

        if ($user->phone !== $data['phone']) {
            $user->phone = $data['phone'];
            $user->save();
        }

        $service->saveSetup($user, $data['birth_date'], $data['mother_name'], (int) $data['question_id'], $data['answer']);
        $service->syncIdentityToEmployee($user, $data['birth_date'], $data['phone']);

        return redirect()->intended(route('menu'));
    }
}
