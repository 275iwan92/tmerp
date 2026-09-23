<?php

namespace Devlib\Components;

// Grid spreadsheet (perilaku DataTables Editor + AutoFill + KeyTable, buatan sendiri -- Editor asli berbayar).
// Klik sel, panah/Tab pindah, ketik = edit, Enter/F2 edit, Esc batal, Shift/drag = rentang, Ctrl+C/Ctrl+V
// dengan Excel, pegangan isi di pojok seleksi (salin / lanjutkan deret angka), Ctrl+Z / Ctrl+Y, Delete kosongkan.
// columns: [['key','label','type' => text|number|money|select|date|check,'options','required','readonly','width']]
// Simpan Semua -> validasi -> event dl:sheet-save {rows, changed}. Sel berubah ditandai; salah dimerahkan.
class Sheet extends DevlibComponent
{
    public function __construct(
        public array $columns = [],
        public array $rows = [],
        public string $rowKey = 'id',
        public string $title = '',
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::sheet';
    }

    public function source(): string
    {
        return $this->source ?? 'tabel master (edit massal)';
    }

    public function config(): array
    {
        return ['columns' => array_map(function ($c) {
            $c += ['type' => 'text', 'required' => false, 'readonly' => false];
            if (isset($c['options']) && array_is_list($c['options'])) {
                $c['options'] = array_combine($c['options'], $c['options']);
            }

            return $c;
        }, $this->columns), 'rowKey' => $this->rowKey];
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-sheet') }} {!! $dl() !!} data-dl-sheet data-config="{{ json_encode($config()) }}" data-rows="{{ json_encode($rows) }}">
                <div class="dl-sheet__bar">
                    @if ($title)<b>{{ $title }}</b>@endif
                    <x-dl::button variant="secondary" size="sm" icon="new" label="Baris" data-s-add />
                    <x-dl::button variant="secondary" size="sm" icon="delete" label="Hapus baris" data-s-del />
                    <x-dl::icon-button icon="undo" title="Undo (Ctrl+Z)" data-s-undo />
                    <x-dl::icon-button icon="fa-rotate-right" title="Redo (Ctrl+Y)" data-s-redo />
                    <span class="dl-sheet__info" data-s-info></span>
                    <span class="dl-print__spacer"></span>
                    <x-dl::button preset="save" size="sm" label="Simpan Semua" data-s-save />
                </div>
                <div class="dl-sheet__wrap" tabindex="0" data-s-wrap aria-label="Lembar kerja"><table class="dl-sheet__table" data-s-table></table></div>
                <p class="dl-sheet__help">Ketik langsung untuk mengisi · Enter/F2 edit · Tab/panah pindah · Shift/drag pilih rentang · Ctrl+C / Ctrl+V dengan Excel · tarik kotak kecil di pojok seleksi untuk mengisi ke bawah · Ctrl+Z undo</p>
            </div>
            BLADE;
    }
}
