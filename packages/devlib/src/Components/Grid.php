<?php

namespace Devlib\Components;

// Grid induk (griduistandard + perilaku tabel Filament/PowerGrid yang dipilih user).
// Layar cukup MENDEKLARASIKAN; semua perilaku ada di sini + devlib.js (initGrid):
//   cari semua kolom tampil, sort (panah selalu tampil), Filters + chip, Tab, kotak
//   statistik = filter, pilih kolom, kerapatan + baris per sel, grup lipat + subtotal,
//   centang (Shift = rentang) + aksi massal, baris bisa dibuka, klik 2x = buka (LOCKED),
//   export CSV = kolom yang tampil, pagination ala ScriptCase, setelan diingat per grid.
//
// columns: [['key', 'label', 'type' => text|identity|number|money|date|badge|toggle,
//            'code' => kolom kode (identity), 'avatar' => false (identity tanpa bulatan), 'tones' => [nilai => tone] (badge),
//            'fallback' => kolom nama (image: tanpa foto -> inisial di kotak yang sama),
//            'visible' => true, 'sortable' => true, 'sum' => false (money/number)]]
// stats/tabs: [['label', 'where' => [kolom => nilai|[nilai..]], 'tone']]  (tanpa where = total)
// actions: master (Edit + Aktif/Nonaktif, Pilihan C) | worklist (Buka + Confirm) | none
// Tipe kolom tambahan: image (thumbnail + perbesar), progress (0-100), steps ('steps' => [tahap..], nilai = tahap sekarang),
//   toggle (saklar, bisa diubah -> dl:grid-cell), rating, spark (array angka), button ('text', 'action' -> dl:grid-action).
// detail: ['key' => kolom array baris anak, 'columns' => [[key,label,type]], 'title'] -> baris dibuka = tabel detail bersarang.
// cards: ['image','title','sub','badge','tones','meta' => [kolom..]] -> tombol Tabel/Kartu (CardView).
// openUrl: alamat buka baris, {id} diganti nilai rowKey (klik 2x / tombol Buka / Edit langsung pindah layar).
// quickAdd: [kolom..] -> baris isian cepat di atas tabel (Enter = tambah, event dl:grid-add). groupBy = grup awal.
// mode (user 2026-09-26, dua keluarga grid yang dipisah):
//   entry  = Grid for Pre-Data Entry: baris lega, centang + kotak ikon Aksi per baris, + New Record; pintu ke form.
//            Grid for Master: kolom pertama = kotak foto (image + fallback inisial), identity tanpa bulatan avatar.
//   report = Grid for Reporting: rapat (banyak baris per layar, data ribuan), TANPA centang/Aksi/New Record,
//            fokus cari + Filters terbuka di atas tabel + saring per kolom (teks mengandung; angka boleh >, <, >=, <=, =).
// confirmWhen: baris worklist yang boleh di-Confirm ([kolom => nilai|[nilai..]]), per layar (Quotation = DRAFT, PO = SUBMITTED).
class Grid extends DevlibComponent
{
    public function __construct(
        public string $id = 'grid',
        public array $columns = [],
        public array $rows = [],
        public string $rowKey = 'id',
        public array $stats = [],
        public array $tabs = [],
        public array $filters = [],
        public array $groups = [],
        public array $bulk = [],
        public array $expand = [],
        public string $actions = 'master',
        public ?string $activeKey = 'active',
        public ?string $newRecord = '+ New Record',
        public ?int $perPage = null,
        public array $detail = [],
        public array $cards = [],
        public array $quickAdd = [],
        public ?string $groupBy = null,
        public string $search = 'Cari...',
        public ?string $source = null,
        public ?string $openUrl = null,
        public string $mode = 'entry',
        public array $confirmWhen = ['status' => 'SUBMITTED'],
    ) {
        if ($this->isReport()) {
            $this->actions = 'none';
            $this->newRecord = null;
            $this->bulk = [];
            $this->quickAdd = [];
        }
        $this->perPage ??= $this->isReport() ? 100 : 25;
    }

    public function isReport(): bool
    {
        return $this->mode === 'report';
    }

    public function tag(): string
    {
        return 'x-dl::grid id='.$this->id;
    }

    public function source(): string
    {
        return $this->source ?? 'baris data grid';
    }

    public function config(): array
    {
        return [
            'id' => $this->id, 'columns' => array_values(array_map(fn ($c) => $c + ['type' => 'text', 'visible' => true, 'sortable' => true], $this->columns)),
            'rowKey' => $this->rowKey, 'stats' => $this->stats, 'tabs' => $this->tabs, 'filters' => $this->filters, 'groups' => $this->groups,
            'bulk' => $this->bulk, 'expand' => $this->expand, 'actions' => $this->actions, 'activeKey' => $this->activeKey, 'perPage' => $this->perPage,
            'openUrl' => $this->openUrl, 'detail' => $this->detail ?: null, 'cards' => $this->cards ?: null, 'quickAdd' => $this->quickAdd, 'groupBy' => $this->groupBy,
            'mode' => $this->mode, 'confirmWhen' => $this->confirmWhen, 'pageSizes' => $this->isReport() ? [50, 100, 250, 500] : [10, 25, 50, 100],
        ];
    }

    public function render()
    {
        return view('devlib::components.grid');
    }
}
