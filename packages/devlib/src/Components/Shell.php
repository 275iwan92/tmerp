<?php

namespace Devlib\Components;

// Kerangka aplikasi setelah login (acuan mockup main_menu_v2.html): sidebar
// kontekstual + mega menu modul + tab MDI + Ctrl+K + menu avatar + drawer.
// Menu = data (baris setara sys_app_menu), bukan markup: layar pemakai cukup
// menyerahkan array $modules.
//
// $modules = [
//   ['code' => 'SALES', 'short' => 'SLS' (opsional: tampil di bilah atas, nama lengkap di tooltip), 'label' => 'Sales', 'icon' => 'fa-solid fa-…', 'children' => [
//       ['label' => 'Transaksi', 'items' => [ITEM, …]],   // grup
//       ITEM,                                              // item langsung
//   ]],
// ]
// ITEM = ['code', 'label', 'url', 'type' => form|grid|report, 'icon'?, 'badge'?, 'badgeTone'?]
// $chat = ['me', 'people', 'convos', 'api'] -> Chat (x-dl::chat, versi ringkas); 'api' kosong = pratinjau tanpa server.
// $open = code item yang dibuka sebagai tab saat mulai (bila tidak ada tab yang dipulihkan dari sesi browser).
// Turunan: LayerShell (x-dl::layer-shell) = kerangka yang sama, navigasi Layer Menu tanpa top bar.
// Layar pemakai memanggil <x-dynamic-component :component="Shell::preferred()"> supaya pilihan menu user ikut.
class Shell extends DevlibComponent
{
    public function __construct(
        public array $modules = [],
        public array $user = ['name' => 'User', 'email' => '', 'role' => ''],
        public string $brand = 'TELASIH MAKMUR',
        public string $tagline = 'packaging solution',
        // Url gambar logo APLIKASI; kosong = lencana gradasi bawaan. Bukan logo perusahaan (itu untuk kop cetakan).
        public ?string $logo = null,
        public string $logoutAction = '',
        public string $menuSource = 'sys_app_menu',
        public bool $developer = false,
        public array $chat = [],
        public ?string $open = null,
        // false = pilihan tukar ke Menu Classic disembunyikan (situs ERP v2 hanya Layer Menu; Classic tetap ada di katalog DevLib).
        public bool $classic = true,
        // Halaman Bantuan & dukungan (dibuka sebagai tab); kosong = pesan "segera".
        public ?string $helpUrl = null,
        // Halaman profil Layer Menu yang dibuka saat mulai (mis. kembali ke Tiket Bantuan sesudah kirim tiket).
        public ?string $profilePage = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::shell';
    }

    public function source(): string
    {
        return 'menu = '.$this->menuSource;
    }

    // Menu pilihan user (cookie dl_menu, diset JS saat menukar menu). Bawaan = Layer Menu.
    // Dibaca dari $_COOKIE mentah: cookie ini ditulis browser, bukan cookie terenkripsi Laravel.
    public static function preferred(): string
    {
        return ($_COOKIE['dl_menu'] ?? 'layer') === 'classic' ? 'dl::shell' : 'dl::layer-shell';
    }

    public function render()
    {
        return view('devlib::components.shell');
    }
}
