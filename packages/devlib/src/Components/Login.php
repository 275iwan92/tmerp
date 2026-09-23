<?php

namespace Devlib\Components;

// Layar login (ala ScriptCase: beberapa model tampilan, isi & perilaku sama).
// split   = acuan mockup login.html (hero kiri, panel kanan bisa dilebarkan)
// mirror  = panel kiri, hero kanan
// center  = kartu di tengah latar gradasi
// fullbg  = foto penuh + kartu kaca
// minimal = polos putih, logo di atas
class Login extends DevlibComponent
{
    public const MODELS = [
        'split' => 'Split (mockup)',
        'mirror' => 'Split terbalik',
        'center' => 'Kartu tengah',
        'fullbg' => 'Foto penuh',
        'minimal' => 'Minimal',
    ];

    public function __construct(
        public string $action = '',
        public string $model = 'split',
        public ?string $error = null,
        public string $username = '',
        public string $brand = 'TELASIH MAKMUR',
        public string $tagline = 'packaging solution',
        public string $forgotUrl = '#',
        public string $registerUrl = '#',
        public array $heroImages = [
            'https://upload.wikimedia.org/wikipedia/commons/e/ea/Offset_press_4_color.jpg',
            'https://upload.wikimedia.org/wikipedia/commons/e/ed/Offset_printing_press.jpg',
        ],
        public array $quotes = [
            ['t' => 'Cara memulai adalah berhenti bicara dan mulai melakukan.', 'a' => 'Walt Disney'],
            ['t' => 'Kesuksesan adalah hasil dari persiapan, kerja keras, dan belajar dari kegagalan.', 'a' => 'Colin Powell'],
            ['t' => 'Disiplin adalah jembatan antara tujuan dan pencapaian.', 'a' => 'Jim Rohn'],
            ['t' => 'Fokus pada progres, bukan kesempurnaan.', 'a' => 'Anonim'],
        ],
    ) {
        $this->model = array_key_exists($model, self::MODELS) ? $model : 'split';
    }

    public function tag(): string
    {
        return 'x-dl::login model='.$this->model;
    }

    public function source(): string
    {
        return 'Tanpa DB — akun di config aplikasi pemakai; hero = sys_login_hero, kutipan = sys_quotes (rencana mockup)';
    }

    public function hasHero(): bool
    {
        return in_array($this->model, ['split', 'mirror', 'fullbg'], true);
    }

    public function render()
    {
        return view('devlib::components.login');
    }
}
