<?php

namespace Devlib\Components;

// Isi kotak Daftar Akun (mockup register.html), dipasang di x-dl::frame seperti login.
// Daftar sebagai (Customer / Supplier / Internal) -> NIK 16 digit atau NRP; email diverifikasi (username otomatis
// dari email); No. WhatsApp diverifikasi lewat OTP; password min. 8 huruf+angka + konfirmasi. Akun baru menunggu
// persetujuan admin (grup & hak akses ditentukan admin). Event (cancelable, bila tidak dicegah = pratinjau lolos):
// dl:verify-email {email}, dl:send-otp {phone}, dl:verify-otp {phone, code}, dl:register {data}.
// Aplikasi yang memverifikasi sendiri memanggil el.dlRegister.verified('email'|'wa', ok, pesan).
class RegisterBox extends DevlibComponent
{
    public function __construct(
        public string $action = '',
        public string $loginUrl = '#',
        public array $types = ['customer' => 'Customer', 'supplier' => 'Supplier', 'internal' => 'Internal User'],
    ) {}

    public function tag(): string
    {
        return 'x-dl::register-box';
    }

    public function source(): string
    {
        return 'sec_user (activate=0, menunggu persetujuan admin)';
    }

    public function render()
    {
        return view('devlib::components.register-box');
    }
}
