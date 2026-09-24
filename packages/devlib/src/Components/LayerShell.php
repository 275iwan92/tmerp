<?php

namespace Devlib\Components;

// Kerangka aplikasi dengan navigasi x-dl::layer-menu di kiri dan TANPA top bar. Tab MDI, laci, modal
// dan palet Ctrl+K diwarisi dari Shell (partial shell/work & shell/overlays). Semua isi menu avatar
// Shell pindah ke roda gigi Layer Menu; chat tinggal di Layer Menu (tukar / dok kanan).
class LayerShell extends Shell
{
    public function tag(): string
    {
        return 'x-dl::layer-shell';
    }

    public function jsClass(): ?string
    {
        return 'LayerShell';
    }

    // Modul Shell sebagai tree Layer Menu: modul -> item, grup = caption. Kunci daun = code item (= tab MDI).
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

    public function actions(): array
    {
        return array_values(array_filter([
            ['key' => 'palette', 'label' => 'Cari (Ctrl K)', 'icon' => 'search'],
            ['key' => 'notif', 'label' => 'Notifikasi', 'icon' => 'notification'],
            ['key' => 'ai', 'label' => 'Asisten AI', 'icon' => 'ai'],
            ['key' => 'edit', 'label' => 'Edit profil', 'icon' => 'fa-user-pen'],
            ['key' => 'pass', 'label' => 'Ubah password', 'icon' => 'password'],
            ['key' => 'help', 'label' => 'Bantuan & dukungan', 'icon' => 'help'],
            $this->developer ? ['key' => 'devmode', 'label' => 'Mode Developer', 'icon' => 'fa-bug'] : null,
            ['key' => 'menu-classic', 'label' => 'Menu Classic', 'icon' => 'fa-table-columns'],
            ['key' => 'logout', 'label' => 'Keluar aplikasi', 'icon' => 'logout', 'tone' => 'danger', 'confirm' => 'Keluar dari aplikasi?'],
        ]));
    }

    public function render()
    {
        return view('devlib::components.layer-shell');
    }
}
