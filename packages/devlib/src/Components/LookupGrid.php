<?php

namespace Devlib\Components;

// Grid Lookup (Grid Pre-Entry): daftar bantu kecil yang jarang berubah (Agama, Golongan Darah, ...), diisi langsung di sel.
// = Sheet mode lookup: Cari + Baris + Batal + Simpan, rapat, tanpa Hapus; kolom 'unique' => true tidak boleh kembar.
class LookupGrid extends Sheet
{
    public function __construct(
        array $columns = [],
        array $rows = [],
        string $rowKey = 'id',
        string $title = '',
        ?string $source = null,
    ) {
        parent::__construct($columns, $rows, $rowKey, $title, $source, lookup: true);
    }

    public function tag(): string
    {
        return 'x-dl::lookup-grid';
    }

    public function source(): string
    {
        return $this->source ?? 'tabel lookup (daftar bantu)';
    }
}
