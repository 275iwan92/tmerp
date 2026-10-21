<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\LoginService;
use App\Services\Auth\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/*
 * REMARK
 * Tujuan       : Halaman daftar akun (x-dl::frame + x-dl::register-box): form pendaftaran dan simpan akun yang menunggu persetujuan administrator.
 * Sumber hulu  : App\Services\Auth\RegistrationService (aturan, login turunan email, notifikasi admin), LoginService (data latar).
 * Input        : GET /register; POST /register (reg_type, name, email, whatsapp, id_no, password, password_confirmation).
 * Output       : View auth.register; sukses = form tampil sudah terkirim (flash registered), galat validasi = kembali dengan pesan per kolom.
 * Dipakai oleh : routes/web.php (middleware guest).
 * Status       : Aktif (T10 tahap 6a, 2026-10-21). Menggantikan halaman v1 pages::auth.register.
 */
class RegisterController extends Controller
{
    public function show(LoginService $login): View
    {
        return view('auth.register', ['images' => $login->heroImages(), 'quotes' => $login->quotes()]);
    }

    public function store(Request $request, RegistrationService $registration): RedirectResponse
    {
        $data = $request->validate(RegistrationService::rules((string) $request->input('reg_type')), [], [
            'password' => 'password', 'id_no' => $request->input('reg_type') === 'internal' ? 'NRP' : 'NIK', 'whatsapp' => 'WhatsApp',
        ]);
        $registration->register($data);

        return redirect()->route('register')->with('registered', true);
    }
}
