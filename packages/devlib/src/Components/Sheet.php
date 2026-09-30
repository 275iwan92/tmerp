<?php

namespace Devlib\Components;

// Grid spreadsheet (perilaku DataTables Editor + AutoFill + KeyTable, buatan sendiri -- Editor asli berbayar).
// Klik sel, panah/Tab pindah, ketik = edit, Enter/F2 edit, Esc batal, Shift/drag = rentang, Ctrl+C/Ctrl+V
// dengan Excel, pegangan isi di pojok seleksi (salin / lanjutkan deret angka), Ctrl+Z / Ctrl+Y, Delete kosongkan.
// columns: [['key','label','type' => text|number|money|select|date|time|check,'options','required','readonly','width']]
// Kolom teks: badge aa/Aa/AA di sel yang diedit (seperti x-dl::field.text), diingat per kolom; 'case' => uppercase|capitalize = awal.
// Kolom angka: 'decimals' => n = selalu tampil n digit (18.450,00). Edit seperti sel Excel: angka mentah (1250000,5), format saat keluar sel.
// Kolom date: tampil dd-Mmm-yyyy, ketik 10-10-26 / 10 okt / 2026-10-10 (disimpan YYYY-MM-DD). time: ketik 8 / 830 / 8.30 -> 08:30.
// Save All -> validasi -> event dl:sheet-save {rows, changed}. Sel berubah ditandai; salah dimerahkan.
// lookup=true (turunan LookupGrid = Grid Lookup): daftar bantu kecil -- Cari + Baris + Undo + Save saja, rapat,
// tanpa Hapus (baris yang sudah dipakai cukup dinonaktifkan), kolom 'unique' => true tidak boleh kembar.
// saveUrl: Save mengirim baris berubah (POST JSON {changed}) ke server; jawaban {rows} = isi terbaru dari database
//   (baris baru dapat id asli), {error} = gagal (perubahan tetap di layar). Tanpa saveUrl = pratinjau (event dl:sheet-save saja).
class Sheet extends DevlibComponent
{
    public function __construct(
        public array $columns = [],
        public array $rows = [],
        public string $rowKey = 'id',
        public string $title = '',
        public ?string $source = null,
        public bool $lookup = false,
        public ?string $saveUrl = null,
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
        }, $this->columns), 'rowKey' => $this->rowKey, 'lookup' => $this->lookup,
            'saveUrl' => $this->saveUrl, 'csrf' => $this->saveUrl ? csrf_token() : null];
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-sheet', 'dl-sheet--lookup' => $lookup]) }} {!! $dl() !!} data-dl-sheet data-config="{{ json_encode($config()) }}" data-rows="{{ json_encode($rows) }}">
                @if ($lookup)
                <div class="dl-sheet__bar">
                    <div class="dl-grid__search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="Cari..." data-s-search aria-label="Cari"></div>
                    <span class="dl-sheet__info" data-s-info></span>
                    <span class="dl-print__spacer"></span>
                    <x-dl::button variant="secondary" size="sm" icon="new" label="Baris" data-s-add />
                    <x-dl::button variant="secondary" size="sm" icon="undo" label="Undo" data-s-revert />
                    <x-dl::button preset="save" size="sm" label="Save" data-s-save />
                </div>
                @else
                <div class="dl-sheet__bar">
                    @if ($title)<b>{{ $title }}</b>@endif
                    <x-dl::button variant="secondary" size="sm" icon="new" label="Baris" data-s-add />
                    <x-dl::button variant="secondary" size="sm" icon="delete" label="Delete Row" data-s-del />
                    <x-dl::icon-button icon="undo" title="Undo (Ctrl+Z)" data-s-undo />
                    <x-dl::icon-button icon="fa-rotate-right" title="Redo (Ctrl+Y)" data-s-redo />
                    <span class="dl-sheet__info" data-s-info></span>
                    <span class="dl-print__spacer"></span>
                    <x-dl::button preset="save" size="sm" label="Save All" data-s-save />
                </div>
                @endif
                <div class="dl-sheet__wrap" tabindex="0" data-s-wrap aria-label="Lembar kerja"><table class="dl-sheet__table" data-s-table></table></div>
                @unless ($lookup)<p class="dl-sheet__help">Ketik langsung untuk mengisi · Enter/F2 edit · Tab/panah pindah · Shift/drag pilih rentang · Ctrl+C / Ctrl+V dengan Excel · tarik kotak kecil di pojok seleksi untuk mengisi ke bawah · Ctrl+Z undo</p>@endunless
            </div>
            BLADE;
    }
}
