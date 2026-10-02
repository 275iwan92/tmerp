<?php

namespace Devlib\Components;

// Papan widget (opening screen yang bisa diatur pemiliknya): kisi 12 kolom berisi widget dari katalog aplikasi.
// Customize -> pindah kiri / kanan, lebar, lepas, Add Widget (dari katalog), Reset to Template. Setiap aksi dikirim ke
// $actionUrl (POST JSON {action, key}) dan server mengembalikan {html} papan terbaru -- susunan yang berlaku = yang
// tersimpan di server, layar hanya mengusulkan. Papan juga memuat ulang dirinya saat ada event dl:refresh = 'board'
// (mis. asisten baru saja memasang widget).
//
// $widgets = [[
//   'key', 'title', 'caption', 'icon', 'tone', 'size' => 3|4|6|8|12, 'source' => 'tabel asal',
//   'open' => ['url', 'code', 'label', 'icon'],                     // tombol buka layar sumber (tab)
//   'type' => 'kpi',      'data' => ['value', 'previous', 'target', 'format', 'good_when', 'spark', 'hint']
//   'type' => 'chart',    'data' => ['chart' => bar|line|combo|donut|pie|funnel, 'labels', 'series', 'format', 'height']
//   'type' => 'progress', 'data' => ['rows' => [['label', 'sub', 'actual', 'target', 'format', 'good_when', 'icon', 'tone']]]
//   'type' => 'ranking',  'data' => ['name_label', 'format', 'rows' => [['name', 'sub', 'target', 'actual']]]
//   'type' => 'list',     'data' => ['rows' => [['title', 'sub', 'value', 'tone']], 'empty' => 'teks bila kosong']
//   'type' => 'quote',    'data' => ['text', 'author', 'day', 'days']
// ], ...]
// $catalog = [['key', 'title', 'description', 'group', 'icon', 'installed' => bool], ...]   // semua widget yang boleh dipasang user ini
class WidgetBoard extends DevlibComponent
{
    public const SIZES = [3, 4, 6, 8, 12];

    public function __construct(
        public array $widgets = [],
        public array $catalog = [],
        public ?string $actionUrl = null,
        public bool $editable = true,
        public bool $editing = false,
        public ?string $title = null,
        public string $id = 'wboard',
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::widget-board';
    }

    public function source(): string
    {
        return $this->source ?? 'katalog widget + susunan papan per user';
    }

    // Pencapaian terhadap target dalam persen; good_when = down berarti makin kecil makin baik.
    public static function pct(array $row): float
    {
        $actual = (float) ($row['actual'] ?? 0);
        $target = (float) ($row['target'] ?? 0);
        if (($row['good_when'] ?? 'up') === 'down') {
            return $actual > 0 ? $target / $actual * 100 : 100;
        }

        return $target > 0 ? $actual / $target * 100 : 0;
    }

    public static function tone(float $pct): string
    {
        return $pct >= 100 ? 'success' : ($pct >= 85 ? 'warn' : 'danger');
    }

    public function render()
    {
        return view('devlib::components.widget-board');
    }
}
