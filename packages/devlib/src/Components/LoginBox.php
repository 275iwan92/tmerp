<?php

namespace Devlib\Components;

// Isi kotak login. Cukup SEBUT bagian yang tampil, berurutan, dipisah koma:
//   lang, support, brand, title, username, email, password, remember, forgot, button, register
// (support = ikon bantuan berisi akun uji coba UAT, tampil di baris atas bersama bahasa)
// Tiap bagian = komponen library (field.text, field.password, button, divider, lang-switch, ...).
class LoginBox extends DevlibComponent
{
    public const PARTS = ['lang', 'brand', 'title', 'username', 'email', 'password', 'remember', 'forgot', 'button', 'register', 'support'];

    public function __construct(
        public string $show = 'lang, title, username, password, forgot, button, register',
        public string $action = '',
        public string $button = 'Masuk',
        public ?string $error = null,
        public string $username = '',
        public string $forgotUrl = '#',
        public string $registerUrl = '#',
        public string $brand = 'TELASIH MAKMUR',
        public string $tagline = 'packaging solution',
    ) {}

    public function tag(): string
    {
        return 'x-dl::login-box show="'.$this->show.'"';
    }

    public function parts(): array
    {
        return array_values(array_intersect(array_map('trim', explode(',', $this->show)), self::PARTS));
    }

    public function render()
    {
        return view('devlib::components.login-box');
    }
}
