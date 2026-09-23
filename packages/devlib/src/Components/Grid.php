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
//            'code' => kolom kode (identity), 'tones' => [nilai => tone] (badge),
//            'visible' => true, 'sortable' => true, 'sum' => false (money/number)]]
// stats/tabs: [['label', 'where' => [kolom => nilai|[nilai..]], 'tone']]  (tanpa where = total)
// actions: master (Edit + Aktif/Nonaktif, Pilihan C) | worklist (Buka + Confirm) | none
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
        public int $perPage = 25,
        public string $search = 'Cari...',
        public ?string $source = null,
    ) {}

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
        ];
    }

    public function render()
    {
        return view('devlib::components.grid');
    }
}
