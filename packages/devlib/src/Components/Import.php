<?php

namespace Devlib\Components;

use Devlib\Lookup;

// Import data dari CSV (perilaku ImportAction Filament): 1 Upload (+ template) -> 2 Cocokkan kolom
// (otomatis dari label/key/alias) -> 3 Pratinjau & validasi per sel -> Import baris valid + unduh CSV baris gagal.
// columns: [['key', 'label', 'required', 'type' => text|number|date|lookup, 'lookup' => nama Devlib\Lookup,
//            'aliases' => ['nama lain di header file'], 'example' => contoh isi template]]
// Hasil -> event dl:import (detail: {rows: baris valid ternormalisasi, failed}).
class Import extends DevlibComponent
{
    public function __construct(
        public string $title = 'Import Data',
        public array $columns = [],
        public string $template = 'template-import',
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::import';
    }

    public function source(): string
    {
        return $this->source ?? 'file CSV';
    }

    public function config(): array
    {
        return ['columns' => array_map(function ($c) {
            $c += ['type' => 'text', 'required' => false, 'aliases' => []];
            if ($c['type'] === 'lookup') {
                $l = Lookup::get($c['lookup']);
                $c['lookup'] = ['key' => $l['key'], 'label' => $l['label'], 'rows' => array_map(fn ($r) => [$r[$l['key']], $r[$l['label']]], $l['rows'])];
            }

            return $c;
        }, $this->columns), 'template' => $this->template];
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-import') }} {!! $dl() !!} data-dl-import data-config="{{ json_encode($config()) }}">
                <div class="dl-import__head"><b>{{ $title }}</b><ol class="dl-import__steps" data-i-steps><li>Upload</li><li>Cocokkan kolom</li><li>Pratinjau & import</li></ol></div>
                <div data-i-step="1">
                    <label class="dl-import__drop" data-i-drop><i class="fa-solid fa-file-csv"></i><b>Tarik file CSV ke sini</b><span>atau klik untuk memilih · pemisah koma / titik koma / tab, baris pertama = judul kolom</span><input type="file" accept=".csv,.txt,.tsv,text/csv" data-i-file hidden></label>
                    <div class="dl-row"><x-dl::button variant="secondary" size="sm" icon="download" label="Unduh template CSV" data-i-template /><span class="dl-import__hint">Excel: simpan sebagai CSV (UTF-8) dulu.</span></div>
                </div>
                <div data-i-step="2" hidden><p class="dl-import__hint" data-i-fileinfo></p><div class="dl-import__map" data-i-map></div>
                    <div class="dl-import__foot"><x-dl::button variant="secondary" size="sm" icon="prev" label="Ganti file" data-i-back="1" /><span class="dl-print__spacer"></span><x-dl::button icon="next" label="Pratinjau" data-i-preview /></div></div>
                <div data-i-step="3" hidden><div class="dl-import__stats" data-i-stats></div><label class="dl-choice"><input type="checkbox" data-i-onlybad> Hanya tampilkan baris bermasalah</label><div class="dl-import__wrap"><table class="dl-import__table" data-i-table></table></div>
                    <div class="dl-import__foot"><x-dl::button variant="secondary" size="sm" icon="prev" label="Cocokkan ulang" data-i-back="2" /><x-dl::button variant="secondary" size="sm" icon="download" :label="\Devlib\Lang::text('Download failed rows')" data-i-failed /><span class="dl-print__spacer"></span><x-dl::button preset="submit" label="Import" data-i-go /></div></div>
            </div>
            BLADE;
    }
}
