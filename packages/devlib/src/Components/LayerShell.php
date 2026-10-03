<?php

namespace Devlib\Components;

use Devlib\Lang;

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
        // Item ber-'items' = submenu bertingkat (dibuka sebagai panel berikutnya), selain itu daun.
        $leaf = function (array $it, ?string $group) use (&$leaf, $tone) {
            if (isset($it['items'])) {
                return array_filter(['label' => $it['label'], 'icon' => $it['icon'] ?? null, 'group' => $group,
                    'items' => array_map(fn ($c) => $leaf($c, null), $it['items'])]);
            }

            return array_filter([
                'key' => $it['code'], 'label' => $it['label'], 'icon' => $it['icon'] ?? null, 'group' => $group,
                'badge' => $it['badge'] ?? null, 'tone' => $tone[$it['badgeTone'] ?? ''] ?? null,
            ]);
        };

        // Grup menu = submenu (dibuka ke panel berikutnya, ada breadcrumb), bukan judul + daftar panjang (user 2026-09-26).
        return array_map(fn ($m) => ['label' => $m['label'], 'icon' => $m['icon'] ?? null, 'items' => collect($m['children'] ?? [])
            ->map(fn ($c) => $leaf(isset($c['items']) ? $c + ['icon' => 'fa-solid fa-folder'] : $c, null))
            ->values()->all()], $this->modules);
    }

    // Laporan Manajemen (modul MGMT) punya view sendiri di kepala panel (ikon Operasional | Laporan Manajemen).
    public const REPORT_MODULE = 'MGMT';

    public function views(): array
    {
        $ops = array_values(array_filter($this->modules, fn ($m) => $m['code'] !== self::REPORT_MODULE));
        $rep = array_values(array_filter($this->modules, fn ($m) => $m['code'] === self::REPORT_MODULE));
        if (! $rep || ! $ops) {
            return [];
        }
        $saved = $this->modules;
        $this->modules = $ops;
        $opsTree = $this->layerTree();
        $this->modules = $rep;
        $repTree = $this->layerTree()[0]['items'];
        $this->modules = $saved;

        return [
            ['key' => 'ops', 'label' => Lang::text('Operational'), 'icon' => 'fa-table-cells-large', 'items' => $opsTree],
            ['key' => 'mgmt', 'label' => $rep[0]['label'], 'icon' => 'fa-chart-line', 'items' => $repTree],
        ];
    }

    // View awal: dibuka dengan tab laporan (mis. pendaratan Direksi) -> view Laporan Manajemen.
    public function startView(): ?string
    {
        $rep = collect($this->modules)->firstWhere('code', self::REPORT_MODULE);
        $codes = collect($rep['children'] ?? [])->flatMap(fn ($c) => $c['items'] ?? [$c])->pluck('code');

        return $this->open && $codes->contains($this->open) ? 'mgmt' : null;
    }

    public function actions(): array
    {
        return array_values(array_filter([
            // Urutan panel Profile = keputusan user 2026-10-03: Notifications, Chat, AI Assistant | Edit profile, Help & support,
            // (Setup = halaman panel, disisipkan sesudah Help), Change password, Developer Mode (saklar), Sign out.
            ['key' => 'notif', 'label' => Lang::text('Notifications'), 'icon' => 'notification'],
            ['key' => 'chat', 'label' => 'Chat', 'icon' => 'fa-comment-dots'],
            ['key' => 'ai', 'label' => Lang::text('AI Assistant'), 'icon' => 'ai'],
            ['key' => 'edit', 'label' => Lang::text('Edit profile'), 'icon' => 'fa-user-pen', 'sep' => true],
            ['key' => 'help', 'label' => Lang::text('Help & support'), 'icon' => 'help'],
            ['key' => 'pass', 'label' => Lang::text('Change password'), 'icon' => 'password'],
            $this->developer ? ['key' => 'devmode', 'label' => Lang::text('Developer Mode'), 'icon' => 'fa-bug'] : null,
            $this->classic ? ['key' => 'menu-classic', 'label' => Lang::text('Classic Menu'), 'icon' => 'fa-table-columns'] : null,
            ['key' => 'logout', 'label' => Lang::text('Sign out'), 'icon' => 'logout', 'tone' => 'danger', 'confirm' => Lang::text('Sign out of the application?')],
        ]));
    }

    public function render()
    {
        return view('devlib::components.layer-shell');
    }
}
