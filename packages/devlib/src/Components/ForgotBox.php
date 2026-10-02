<?php

namespace Devlib\Components;

// Isi kotak Lupa Password. mode="email" (mockup forgot-password.html): isi email -> pesan selalu sama
// ("jika terdaftar, tautan dikirim") demi keamanan. mode="questions" (alur tmerp /forgot-password, tanpa email):
// username + 4 jawaban (tanggal lahir, nama ibu kandung, No. HP terdaftar, pertanyaan keamanan) -> password baru.
// Event (cancelable, bila tidak dicegah = pratinjau lolos): dl:forgot {email} | dl:recover {answers} | dl:reset {password}.
// Aplikasi memanggil el.dlForgot.fail(pesan) bila jawaban salah, atau el.dlForgot.step(n) untuk lanjut.
class ForgotBox extends DevlibComponent
{
    public function __construct(
        public string $mode = 'email',
        public string $action = '',
        public string $loginUrl = '#',
        public string $question = 'What was the name of your first pet?',
    ) {}

    public function tag(): string
    {
        return 'x-dl::forgot-box mode='.$this->mode;
    }

    public function source(): string
    {
        return $this->mode === 'email' ? 'password_reset_tokens (tautan email)' : 'sec_user: tgl lahir, ibu kandung, No. HP, jawaban keamanan';
    }

    public function render()
    {
        return view('devlib::components.forgot-box');
    }
}
