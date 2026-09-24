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
// $chat = ['me', 'people', 'convos'] -> drawer Chat (x-dl::chat, versi ringkas).
class Shell extends DevlibComponent
{
    public function __construct(
        public array $modules = [],
        public array $user = ['name' => 'User', 'email' => '', 'role' => ''],
        public string $brand = 'TELASIH MAKMUR',
        public string $tagline = 'packaging solution',
        public string $logoutAction = '',
        public string $menuSource = 'sys_app_menu',
        public bool $developer = false,
        public array $chat = [],
    ) {}

    public function tag(): string
    {
        return 'x-dl::shell';
    }

    public function source(): string
    {
        return 'menu = '.$this->menuSource;
    }

    // Modul yang sama sebagai tree x-dl::layer-menu (mode navigasi "Layer"): modul -> item, grup = caption.
    // Kunci daun = code item, jadi klik daun membuka tab MDI yang sama dengan menu Classic.
    public function layerTree(): array
    {
        $tone = ['partial' => 'warn', 'done' => 'success', 'danger' => 'danger'];
        $leaf = fn (array $it, ?string $group) => array_filter([
            'key' => $it['code'], 'label' => $it['label'], 'icon' => $it['icon'] ?? null, 'group' => $group,
            'badge' => $it['badge'] ?? null, 'tone' => $tone[$it['badgeTone'] ?? ''] ?? null,
        ]);

        return array_map(fn ($m) => ['label' => $m['label'], 'icon' => $m['icon'] ?? null, 'items' => collect($m['children'] ?? [])
            ->flatMap(fn ($c) => isset($c['items']) ? array_map(fn ($it) => $leaf($it, $c['label']), $c['items']) : [$leaf($c, null)])
            ->values()->all()], $this->modules);
    }

    public function render()
    {
        return view('devlib::components.shell');
    }
}
